<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\CommunityStats;
use App\Analytics\Funnel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const RANGES = ['1' => 'Today', '7' => '7 days', '30' => '30 days', '365' => '1 year'];

    /**
     * Seconds the numbers are kept, per range: with months of events (checked with ~500k) a long
     * range takes seconds to count, and moves slowly anyway. "Count now" (?fresh=1) recounts.
     */
    public const CACHE_SECONDS = ['1' => 120, '7' => 300, '30' => 900, '365' => 3600];

    public function __invoke(Request $request): View
    {
        $days = array_key_exists($request->query('days'), self::RANGES) ? $request->query('days') : '7';
        $since = $days === '1' ? now()->startOfDay() : now()->subDays((int) $days);
        $funnel = new Funnel($since);
        $key = "admin.dashboard.v2.{$days}"; // bump when the cached arrays change shape
        if ($request->boolean('fresh')) {
            Cache::forget($key);
        }

        $numbers = Cache::remember($key, self::CACHE_SECONDS[$days], fn () => self::numbers($days, $since, $funnel));
        // Reports waiting right now must not come from an hour-old cache; this part is cheap.
        $numbers['health']['reports'] = (new CommunityStats($days === '1' ? now()->subDays(6)->startOfDay() : $since))->reportHandling();

        return view('admin.dashboard', $numbers + [
            'days' => $days,
            'ranges' => self::RANGES,
            'recent' => $funnel->recentPlays(), // live, and cheap
        ]);
    }

    /** Everything counted from events and results, as plain arrays (the cache refuses objects). */
    private static function numbers(string $days, Carbon $since, Funnel $funnel): array
    {
        // The same length of time just before, for the change arrows.
        $previous = new Funnel($since->copy()->sub(now()->diffAsCarbonInterval($since)), $since);
        // "Today" alone is a single point, so its chart shows the past week.
        $trend = $days === '1' ? new Funnel(now()->subDays(6)->startOfDay()) : $funnel;

        return [
            'counted_at' => now()->timestamp,
            'summary' => $funnel->summary(),
            'previous' => $previous->summary(),
            'entries' => $funnel->startRateByEntry(),
            'community' => $funnel->community(),
            // From the community tables: growth, weekdays, time to first answer, report handling.
            'health' => (new CommunityStats($days === '1' ? now()->subDays(6)->startOfDay() : $since))->all() + ['today' => $days === '1'],
            'trend' => $trend->trend(),
            'hourly' => $funnel->hourly(),
            'distribution' => $funnel->resultDistribution(),
            'reach' => $funnel->questionReach(),
            'channels' => $funnel->shareChannels(),
            'templates' => $funnel->cardTemplates(),
            'themes' => $funnel->cardThemes(),
            'formats' => $funnel->cardFormats(),
            'opens' => $funnel->placeOpens(),
        ];
    }
}
