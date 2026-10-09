<?php

namespace App\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\QuizResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the V1 growth funnel from analytics_events and quiz_results.
 * Visitor counts are distinct anonymous visitor ids, not page loads.
 */
final class Funnel
{
    public function __construct(
        private readonly CarbonInterface $since,
        private readonly ?CarbonInterface $until = null,
    ) {}

    /**
     * Funnel steps count people (distinct visitor ids), so rates stay within 100% even when someone
     * plays several times; `plays` is the raw number of saved results in the period.
     */
    public function summary(): array
    {
        $visitors = $this->distinctVisitors(['landing_view', 'share_page_view']);
        $started = $this->distinctVisitors(['quiz_started']);
        // Completers among this period's starters, so each step is a subset of the one before.
        $completed = $this->results()->whereIn('visitor_id', $this->events()->where('name', 'quiz_started')->select('visitor_id'))
            ->distinct()->count('visitor_id');
        $plays = $this->results()->count();
        // People, not plays: replays would otherwise inflate the referral numbers.
        $referredPlayers = $this->results()->whereNotNull('referrer_result_id')->whereNotNull('visitor_id')
            ->distinct()->count('visitor_id');
        $originalPlayers = $this->results()->whereNotNull('visitor_id')
            ->whereNotIn('visitor_id', $this->results()->whereNotNull('referrer_result_id')->whereNotNull('visitor_id')->select('visitor_id'))
            ->distinct()->count('visitor_id');
        $referredFromLinks = $this->results()->whereNotNull('referrer_result_id')
            ->whereIn('visitor_id', $this->events()->where('name', 'share_page_view')->select('visitor_id'))
            ->distinct()->count('visitor_id');
        $sharedResults = $this->events()->whereIn('name', ['share_clicked', 'link_copied', 'card_saved'])
            ->whereNotNull('quiz_result_id')->distinct()->count('quiz_result_id');
        $referralVisitors = $this->distinctVisitors(['share_page_view']);

        return [
            'visitors' => $visitors,
            'started' => $started,
            'completed' => $completed,
            'plays' => $plays,
            'start_rate' => self::pct($started, $visitors),
            'completion_rate' => self::pct($completed, $started),
            'shared' => $sharedResults,
            'share_rate' => self::pct($sharedResults, $plays),
            'referral_visitors' => $referralVisitors,
            'referred_players' => $referredPlayers,
            'referral_conversion' => self::pct($referredFromLinks, $referralVisitors),
            // New players brought in per original (non-referred) player.
            'viral_k' => $originalPlayers > 0 ? round($referredPlayers / $originalPlayers, 2) : 0,
        ];
    }

    public const COMMUNITY_VIEWS = ['home_view', 'feed_view', 'post_view', 'ask_view', 'notifications_view'];

    public const COMMUNITY_WRITES = ['post_created', 'answer_created'];

    /**
     * Quiz → community: of the period's quiz players, who went on to each community step (each
     * step a subset of the one before, so rates stay ≤ 100%), plus the community side seen on its
     * own: visitors, sign-ups, writers, and how many of them played the quiz. People, by visitor id.
     */
    public function community(): array
    {
        $ids = fn (Builder $q) => $q->whereNotNull('visitor_id')->distinct()->pluck('visitor_id')->flip();
        $players = $ids($this->results());
        $clicked = $players->intersectByKeys($ids($this->events()->where('name', 'community_clicked')));
        $viewed = $clicked->intersectByKeys($viewers = $ids($this->events()->whereIn('name', self::COMMUNITY_VIEWS)));
        $joined = $viewed->intersectByKeys($signups = $ids($this->events()->where('name', 'signed_up')));
        $wrote = $joined->intersectByKeys($writers = $ids($this->events()->whereIn('name', self::COMMUNITY_WRITES)));

        $everPlayed = fn ($set) => $set->isEmpty() ? 0
            : QuizResult::whereIn('visitor_id', $set->keys())->distinct()->count('visitor_id');

        return [
            'steps' => [
                ['key' => 'players', 'label' => 'Played the quiz', 'count' => $players->count()],
                ['key' => 'clicked', 'label' => 'Clicked into the community', 'count' => $clicked->count()],
                ['key' => 'viewed', 'label' => 'Viewed community pages', 'count' => $viewed->count()],
                ['key' => 'joined', 'label' => 'Signed up', 'count' => $joined->count()],
                ['key' => 'wrote', 'label' => 'Posted or answered', 'count' => $wrote->count()],
            ],
            'viewers' => $viewers->count(),
            'viewers_played' => $everPlayed($viewers),
            'signups' => $this->events()->where('name', 'signed_up')->count(),
            'writers' => $writers->count(),
            'writers_played' => $everPlayed($writers),
            'returning' => $this->returningVisitors(),
            'visitors' => $this->events()->whereNotNull('visitor_id')->distinct()->count('visitor_id'),
            'by_place' => $this->communityClicksByPlace(),
        ];
    }

    /** Clicks from the result page and place sheet into a place's discussions, most first. */
    public function communityClicksByPlace(): array
    {
        $names = Location::pluck('name_bn', 'slug');

        return $this->events()->where('name', 'community_clicked')->whereNotNull('meta->place')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.place')) as place, COUNT(DISTINCT visitor_id) as people")
            ->groupBy('place')->orderByDesc('people')->get()
            ->map(fn ($row) => ['name' => $names[$row->place] ?? $row->place, 'people' => (int) $row->people])->all();
    }

    /** Visitors seen on two or more different days (Dhaka time) within the period. */
    public function returningVisitors(): int
    {
        return (int) DB::query()->fromSub(
            $this->events()->whereNotNull('visitor_id')->groupBy('visitor_id')
                ->havingRaw('COUNT(DISTINCT DATE(created_at)) >= 2')->select('visitor_id'),
            'returning'
        )->count();
    }

    /**
     * Start rate by how people arrived: the landing page or a friend's shared result.
     * A starter counts for an entry only if they also viewed it this period, so rates stay ≤ 100%.
     *
     * @return array{direct: array{visitors:int, started:int, rate:float}, link: array{visitors:int, started:int, rate:float}}
     */
    public function startRateByEntry(): array
    {
        $entry = function (string $view, int $referred) {
            $visitors = $this->distinctVisitors([$view]);
            $started = $this->events()->where('name', 'quiz_started')
                ->whereRaw("CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.referred')), '0') AS UNSIGNED) = ?", [$referred])
                ->whereIn('visitor_id', $this->events()->where('name', $view)->select('visitor_id'))
                ->distinct()->count('visitor_id');

            return ['visitors' => $visitors, 'started' => $started, 'rate' => self::pct($started, $visitors)];
        };

        return ['direct' => $entry('landing_view', 0), 'link' => $entry('share_page_view', 1)];
    }

    /**
     * Visitors, completers and shared results per day (per week past 60 days), oldest first.
     *
     * @return list<array{date:string, visitors:int, completed:int, shared:int}>
     */
    public function trend(): array
    {
        $weekly = $this->since->diffInDays($this->until ?? now()) > 60;
        $bucket = fn (string $col) => $weekly ? "DATE(DATE_SUB({$col}, INTERVAL WEEKDAY({$col}) DAY))" : "DATE({$col})";

        $visitors = $this->events()->whereIn('name', ['landing_view', 'share_page_view'])->whereNotNull('visitor_id')
            ->selectRaw($bucket('created_at').' as d, COUNT(DISTINCT visitor_id) as n')->groupBy('d')->pluck('n', 'd');
        $completed = $this->results()->whereNotNull('visitor_id')
            ->selectRaw($bucket('created_at').' as d, COUNT(DISTINCT visitor_id) as n')->groupBy('d')->pluck('n', 'd');
        $shared = $this->events()->whereIn('name', ['share_clicked', 'link_copied', 'card_saved'])->whereNotNull('quiz_result_id')
            ->selectRaw($bucket('created_at').' as d, COUNT(DISTINCT quiz_result_id) as n')->groupBy('d')->pluck('n', 'd');

        $rows = [];
        $day = $this->since->copy()->startOfDay();
        if ($weekly) {
            $day = $day->startOfWeek();
        }
        $end = ($this->until ?? now())->copy();
        while ($day <= $end) {
            $key = $day->toDateString();
            $rows[] = ['date' => $key, 'visitors' => (int) ($visitors[$key] ?? 0), 'completed' => (int) ($completed[$key] ?? 0), 'shared' => (int) ($shared[$key] ?? 0)];
            $day = $weekly ? $day->addWeek() : $day->addDay();
        }

        return $rows;
    }

    /**
     * Plays and visitors per hour of the day (Asia/Dhaka), one row per date; past 31 days the rows are weekdays
     * instead, so a year still fits on screen.
     *
     * @return array{by:'date'|'weekday', rows:list<array{key:string, plays:list<int>, visitors:list<int>}>, hours:list<int>}
     */
    public function hourly(): array
    {
        $byWeekday = $this->since->diffInDays($this->until ?? now()) > 31;
        // WEEKDAY(): 0 = Monday.
        $row = $byWeekday ? 'WEEKDAY(created_at)' : 'DATE(created_at)';
        $count = fn (Builder $q, string $what) => $q->selectRaw("{$row} as r, HOUR(created_at) as h, {$what} as n")
            ->groupBy('r', 'h')->get()->groupBy('r')->map(fn ($hours) => $hours->pluck('n', 'h'));

        $plays = $count($this->results(), 'COUNT(*)');
        $visitors = $count($this->events()->whereIn('name', ['landing_view', 'share_page_view'])->whereNotNull('visitor_id'), 'COUNT(DISTINCT visitor_id)');

        if ($byWeekday) {
            $keys = range(0, 6);
        } else {
            $keys = [];
            for ($day = ($this->until ?? now())->copy()->startOfDay(); $day >= $this->since->copy()->startOfDay(); $day = $day->subDay()) {
                $keys[] = $day->toDateString();
            }
        }
        $fill = fn ($counts) => array_map(fn ($h) => (int) ($counts[$h] ?? 0), range(0, 23));

        return [
            'by' => $byWeekday ? 'weekday' : 'date',
            'rows' => array_map(fn ($key) => ['key' => (string) $key, 'plays' => $fill($plays[$key] ?? []), 'visitors' => $fill($visitors[$key] ?? [])], $keys),
            // Totals per hour, for the busiest-hour read-out and the column sums.
            'hours' => $fill($this->results()->selectRaw('HOUR(created_at) as h, COUNT(*) as n')->groupBy('h')->pluck('n', 'h')),
        ];
    }

    /** @return list<array{name:string, emoji:string, count:int, pct:float}> */
    public function resultDistribution(): array
    {
        $counts = $this->results()
            ->groupBy('location_id')->pluck(DB::raw('count(*)'), 'location_id');
        $total = $counts->sum();

        return Location::orderBy('sort_order')->get()->map(fn ($l) => [
            'name' => $l->name_bn, 'emoji' => $l->emoji,
            'count' => (int) ($counts[$l->id] ?? 0), 'pct' => self::pct((int) ($counts[$l->id] ?? 0), $total),
        ])->sortByDesc('count')->values()->all();
    }

    /** Distinct visitors who answered question N, for spotting drop-off. */
    public function questionReach(): array
    {
        return $this->events()->where('name', 'question_answered')
            ->selectRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.q')) AS UNSIGNED) as q, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy('q')->orderBy('q')->pluck('visitors', 'q')->all();
    }

    public function shareChannels(): array
    {
        return $this->events()->where('name', 'share_clicked')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.channel')) as channel, COUNT(*) as total")
            ->groupBy('channel')->orderByDesc('total')->pluck('total', 'channel')->all()
            + ['link_copied' => $this->events()->where('name', 'link_copied')->count(),
                'card_saved' => $this->events()->where('name', 'card_saved')->count()];
    }

    /** Which share-card design was saved or shared natively. */
    public function cardTemplates(): array
    {
        return $this->cardChoice('template', 'passport');
    }

    /** Which share-card colour theme was saved or shared natively. */
    public function cardThemes(): array
    {
        return $this->cardChoice('theme', 'place');
    }

    /**
     * Places opened from the result page's explore sheets, most opened first.
     *
     * @return list<array{name:string, emoji:string, count:int}>
     */
    public function placeOpens(): array
    {
        $counts = $this->events()->where('name', 'place_opened')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.place')) as slug, COUNT(*) as total")
            ->groupBy('slug')->pluck('total', 'slug');

        return Location::orderBy('sort_order')->get()
            ->filter(fn ($l) => isset($counts[$l->slug]))
            ->map(fn ($l) => ['name' => $l->name_bn, 'emoji' => $l->emoji, 'count' => (int) $counts[$l->slug]])
            ->sortByDesc('count')->values()->all();
    }

    /**
     * The latest plays in the period, newest first.
     *
     * @return list<array{code:string, at:Carbon, name:?string, emoji:string, place:string, pct:int, friend:bool}>
     */
    public function recentPlays(int $limit = 8): array
    {
        return $this->results()->with('location')->latest('id')->limit($limit)->get()
            ->map(fn (QuizResult $r) => [
                'code' => $r->code, 'at' => $r->created_at, 'name' => $r->display_name,
                'emoji' => $r->location?->emoji ?? '', 'place' => $r->location?->name_bn ?? '?',
                'pct' => $r->match_pct, 'friend' => (bool) $r->referrer_result_id,
            ])->all();
    }

    /** Which share-card size (story or square) was saved or shared natively. */
    public function cardFormats(): array
    {
        return $this->cardChoice('format', 'story');
    }

    /** Counts of a card meta field (older events without it count as $default). */
    private function cardChoice(string $field, string $default): array
    {
        return $this->events()
            ->where(fn ($q) => $q->where('name', 'card_saved')
                ->orWhere(fn ($q) => $q->where('name', 'share_clicked')->where('meta->channel', 'native')))
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.{$field}')), ?) as choice, COUNT(*) as total", [$default])
            ->groupBy('choice')->orderByDesc('total')->pluck('total', 'choice')->all();
    }

    private function events(): Builder
    {
        return AnalyticsEvent::where('created_at', '>=', $this->since)
            ->when($this->until, fn ($q) => $q->where('created_at', '<', $this->until));
    }

    private function results(): Builder
    {
        return QuizResult::where('created_at', '>=', $this->since)
            ->when($this->until, fn ($q) => $q->where('created_at', '<', $this->until));
    }

    private function distinctVisitors(array $names): int
    {
        return $this->events()->whereIn('name', $names)
            ->whereNotNull('visitor_id')->distinct()->count('visitor_id');
    }

    private static function pct(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : 0.0;
    }
}
