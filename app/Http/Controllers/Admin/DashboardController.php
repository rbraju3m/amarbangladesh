<?php

namespace App\Http\Controllers\Admin;

use App\Analytics\Funnel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const RANGES = ['1' => 'Today', '7' => '7 days', '30' => '30 days', '365' => '1 year'];

    public function __invoke(Request $request): View
    {
        $days = array_key_exists($request->query('days'), self::RANGES) ? $request->query('days') : '7';
        $since = $days === '1' ? now()->startOfDay() : now()->subDays((int) $days);
        $funnel = new Funnel($since);
        // The same length of time just before, for the change arrows.
        $previous = new Funnel($since->copy()->sub(now()->diffAsCarbonInterval($since)), $since);
        // "Today" alone is a single point, so its chart shows the past week.
        $trend = $days === '1' ? new Funnel(now()->subDays(6)->startOfDay()) : $funnel;

        return view('admin.dashboard', [
            'days' => $days,
            'ranges' => self::RANGES,
            'summary' => $funnel->summary(),
            'previous' => $previous->summary(),
            'entries' => $funnel->startRateByEntry(),
            'trend' => $trend->trend(),
            'hourly' => $funnel->hourly(),
            'distribution' => $funnel->resultDistribution(),
            'reach' => $funnel->questionReach(),
            'channels' => $funnel->shareChannels(),
            'templates' => $funnel->cardTemplates(),
            'themes' => $funnel->cardThemes(),
            'formats' => $funnel->cardFormats(),
            'opens' => $funnel->placeOpens(),
            'recent' => $funnel->recentPlays(),
        ]);
    }
}
