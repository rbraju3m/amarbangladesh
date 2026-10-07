<?php

namespace App\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\QuizResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the V1 growth funnel from analytics_events and quiz_results.
 * Visitor counts are distinct anonymous visitor ids, not page loads.
 */
final class Funnel
{
    public function __construct(private readonly CarbonInterface $since) {}

    public function summary(): array
    {
        $visitors = $this->distinctVisitors(['landing_view', 'share_page_view']);
        $started = $this->distinctVisitors(['quiz_started']);
        $completed = QuizResult::where('created_at', '>=', $this->since)->count();
        $referredCompleted = QuizResult::where('created_at', '>=', $this->since)->whereNotNull('referrer_result_id')->count();
        $sharedResults = AnalyticsEvent::where('created_at', '>=', $this->since)
            ->whereIn('name', ['share_clicked', 'link_copied', 'card_saved'])
            ->whereNotNull('quiz_result_id')->distinct()->count('quiz_result_id');
        $referralVisitors = $this->distinctVisitors(['share_page_view']);

        return [
            'visitors' => $visitors,
            'started' => $started,
            'completed' => $completed,
            'start_rate' => self::pct($started, $visitors),
            'completion_rate' => self::pct($completed, $started),
            'shared' => $sharedResults,
            'share_rate' => self::pct($sharedResults, $completed),
            'referral_visitors' => $referralVisitors,
            'referred_completed' => $referredCompleted,
            'referral_conversion' => self::pct($referredCompleted, $referralVisitors),
            // New players brought in per original (non-referred) player.
            'viral_k' => ($completed - $referredCompleted) > 0 ? round($referredCompleted / ($completed - $referredCompleted), 2) : 0,
        ];
    }

    /** @return list<array{name:string, emoji:string, count:int, pct:float}> */
    public function resultDistribution(): array
    {
        $counts = QuizResult::where('created_at', '>=', $this->since)
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
        return AnalyticsEvent::where('created_at', '>=', $this->since)->where('name', 'question_answered')
            ->selectRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.q')) AS UNSIGNED) as q, COUNT(DISTINCT visitor_id) as visitors")
            ->groupBy('q')->orderBy('q')->pluck('visitors', 'q')->all();
    }

    public function shareChannels(): array
    {
        return AnalyticsEvent::where('created_at', '>=', $this->since)->where('name', 'share_clicked')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.channel')) as channel, COUNT(*) as total")
            ->groupBy('channel')->orderByDesc('total')->pluck('total', 'channel')->all()
            + ['link_copied' => AnalyticsEvent::where('created_at', '>=', $this->since)->where('name', 'link_copied')->count(),
                'card_saved' => AnalyticsEvent::where('created_at', '>=', $this->since)->where('name', 'card_saved')->count()];
    }

    private function distinctVisitors(array $names): int
    {
        return AnalyticsEvent::where('created_at', '>=', $this->since)->whereIn('name', $names)
            ->whereNotNull('visitor_id')->distinct()->count('visitor_id');
    }

    private static function pct(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round(100 * $part / $whole, 1) : 0.0;
    }
}
