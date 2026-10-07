<?php

namespace App\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\QuizResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
        $referredCompleted = $this->results()->whereNotNull('referrer_result_id')->count();
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
            'referred_completed' => $referredCompleted,
            'referral_conversion' => self::pct($referredCompleted, $referralVisitors),
            // New players brought in per original (non-referred) player.
            'viral_k' => ($plays - $referredCompleted) > 0 ? round($referredCompleted / ($plays - $referredCompleted), 2) : 0,
        ];
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
