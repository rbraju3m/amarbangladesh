<?php

namespace App\Analytics;

use App\Community\Moderation;
use App\Models\AdminAction;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The admin dashboard's community numbers, counted from the community tables themselves (not from
 * analytics events): growth per day, posts by weekday, how fast questions get answered and how fast
 * reports get handled. Times are Asia/Dhaka (the app and MySQL session zone), so DATE()/WEEKDAY() on
 * the stored timestamps are Dhaka days. Everything is returned as plain arrays (the dashboard caches them).
 */
final class CommunityStats
{
    /** Post kinds that ask for an answer, for time to first answer. */
    public const ASKING = ['question', 'help'];

    public function __construct(private Carbon $since, private ?Carbon $until = null) {}

    /**
     * New members, posts and answers (replies included) per day; past 60 days per week (Monday).
     *
     * @return array{weekly: bool, rows: list<array{date: string, members: int, posts: int, answers: int}>}
     */
    public function daily(): array
    {
        $weekly = $this->since->diffInDays($this->until ?? now()) > 60;
        $bucket = $weekly ? 'DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))' : 'DATE(created_at)';
        $count = fn ($query) => $this->range($query)->selectRaw("{$bucket} AS d, COUNT(*) AS n")->groupBy('d')->pluck('n', 'd');
        $members = $count(Member::query());
        $posts = $count(Post::where('status', '!=', Post::DELETED));
        $answers = $count(Answer::where('status', '!=', Post::DELETED));

        $rows = [];
        $day = $this->since->copy()->startOfDay();
        if ($weekly) {
            $day->startOfWeek();
        }
        for ($end = ($this->until ?? now())->copy(); $day <= $end; $weekly ? $day->addWeek() : $day->addDay()) {
            $key = $day->toDateString();
            $rows[] = ['date' => $key, 'members' => (int) ($members[$key] ?? 0), 'posts' => (int) ($posts[$key] ?? 0), 'answers' => (int) ($answers[$key] ?? 0)];
        }

        return ['weekly' => $weekly, 'rows' => $rows];
    }

    /**
     * Posts created on each weekday (authors' own deletions left out).
     *
     * @return list<array{day: string, posts: int}> Monday first
     */
    public function postsByWeekday(): array
    {
        $counts = $this->range(Post::where('status', '!=', Post::DELETED))
            ->selectRaw('WEEKDAY(created_at) AS w, COUNT(*) AS n')->groupBy('w')->pluck('n', 'w');

        return array_map(fn ($w, $day) => ['day' => $day, 'posts' => (int) ($counts[$w] ?? 0)],
            range(0, 6), ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    }

    /**
     * How fast questions and help requests posted in the range got their first answer: a published
     * top-level answer by someone other than the author. The median is over answered posts; "within
     * 1 h / 24 h" counts only posts at least that old, so a post from ten minutes ago doesn't count
     * against the 24-hour rate.
     *
     * @return array{asked: int, answered: int, unanswered: int, median_minutes: ?int, within_1h: ?float, within_24h: ?float}
     */
    public function firstAnswers(): array
    {
        $firstAnswer = Answer::query()->from('answers AS a')->selectRaw('MIN(a.created_at)')
            ->whereColumn('a.post_id', 'posts.id')->whereColumn('a.member_id', '!=', 'posts.member_id')
            ->whereNull('a.parent_id')->where('a.status', Post::PUBLISHED);
        $posts = $this->range(Post::published()->whereIn('type', self::ASKING), 'posts.created_at')
            ->select('posts.created_at')->selectSub($firstAnswer, 'first_answer_at')->toBase()->get();

        $now = $this->until ?? now();
        $minutes = $posts->whereNotNull('first_answer_at')
            ->map(fn ($p) => Carbon::parse($p->created_at)->diffInMinutes(Carbon::parse($p->first_answer_at)))->sort()->values();
        $within = function (int $hours) use ($posts, $now) {
            $old = $posts->filter(fn ($p) => Carbon::parse($p->created_at)->diffInHours($now) >= $hours);
            $fast = $old->filter(fn ($p) => $p->first_answer_at && Carbon::parse($p->created_at)->diffInMinutes(Carbon::parse($p->first_answer_at)) <= $hours * 60);

            return $old->count() ? round(100 * $fast->count() / $old->count(), 1) : null;
        };

        return [
            'asked' => $posts->count(),
            'answered' => $minutes->count(),
            'unanswered' => $posts->count() - $minutes->count(),
            'median_minutes' => $minutes->count() ? (int) round(self::median($minutes->all())) : null,
            'within_1h' => $within(1),
            'within_24h' => $within(24),
        ];
    }

    /**
     * How long reported items waited for an admin: from the first report to the first admin action on
     * them (keep, hide, remove…), from the moderation log, so only from when the log began
     * (2026-10-10). Plus what is waiting right now.
     *
     * @return array{handled: int, median_minutes: ?int, waiting: int, oldest_waiting_minutes: ?int}
     */
    public function reportHandling(): array
    {
        // Admin actions in the range that carry the reports they acted on; one per item and report round.
        $first = [];
        $actions = $this->range(AdminAction::whereNotNull('user_id')->whereNotNull('meta->first_report_at'))
            ->orderBy('id')->get(['target_type', 'target_id', 'meta', 'created_at']);
        foreach ($actions as $action) {
            $key = "{$action->target_type}:{$action->target_id}:{$action->meta['first_report_at']}";
            $first[$key] ??= Carbon::parse($action->meta['first_report_at'])->diffInMinutes($action->created_at);
        }

        // Waiting now: items with reports nobody has acted on since (reports are cleared by a keep).
        $oldest = DB::table('reports')
            ->leftJoin('posts', fn ($j) => $j->where('reports.reportable_type', 'post')->whereColumn('posts.id', 'reports.reportable_id'))
            ->leftJoin('answers', fn ($j) => $j->where('reports.reportable_type', 'answer')->whereColumn('answers.id', 'reports.reportable_id'))
            ->whereRaw('COALESCE(posts.status, answers.status) IN (?, ?)', [Post::PUBLISHED, Post::HIDDEN])
            ->min('reports.created_at');

        return [
            'handled' => count($first),
            'median_minutes' => $first ? (int) round(self::median(array_values($first))) : null,
            'waiting' => Moderation::needsLookCount(),
            'oldest_waiting_minutes' => $oldest ? (int) Carbon::parse($oldest)->diffInMinutes(now()) : null,
        ];
    }

    /**
     * Reads (App\Community\Views) of the published posts created in the range: total, by category,
     * and the most-read posts. Counts are lifetime per post (no per-day history is kept).
     *
     * @return array{total: int, posts: int, by_category: list<array{name: string, views: int, posts: int}>, top: list<array{id: int, title: string, views: int}>}
     */
    public function views(): array
    {
        $posts = $this->range(Post::published(), 'posts.created_at');
        $byCategory = (clone $posts)->leftJoin('categories', 'categories.id', '=', 'posts.category_id')
            ->groupBy('categories.name_en')->selectRaw('categories.name_en AS name, SUM(posts.views_count) AS views, COUNT(*) AS n')
            ->orderByDesc('views')->get()
            ->map(fn ($r) => ['name' => $r->name ?? 'No topic', 'views' => (int) $r->views, 'posts' => (int) $r->n])->all();

        return [
            'total' => array_sum(array_column($byCategory, 'views')),
            'posts' => array_sum(array_column($byCategory, 'posts')),
            'by_category' => $byCategory,
            'top' => (clone $posts)->where('views_count', '>', 0)->orderByDesc('views_count')->orderByDesc('id')->limit(5)
                ->get(['id', 'title', 'views_count'])->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'views' => $p->views_count])->all(),
        ];
    }

    /** Everything above, for the dashboard. */
    public function all(): array
    {
        return [
            'daily' => $this->daily(),
            'weekdays' => $this->postsByWeekday(),
            'first_answers' => $this->firstAnswers(),
            'reports' => $this->reportHandling(),
            'views' => $this->views(),
        ];
    }

    private function range($query, string $column = 'created_at')
    {
        return $query->where($column, '>=', $this->since)->when($this->until, fn ($q) => $q->where($column, '<', $this->until));
    }

    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
