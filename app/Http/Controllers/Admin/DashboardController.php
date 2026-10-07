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
        $funnel = new Funnel($days === '1' ? now()->startOfDay() : now()->subDays((int) $days));

        return view('admin.dashboard', [
            'days' => $days,
            'ranges' => self::RANGES,
            'summary' => $funnel->summary(),
            'distribution' => $funnel->resultDistribution(),
            'reach' => $funnel->questionReach(),
            'channels' => $funnel->shareChannels(),
            'templates' => $funnel->cardTemplates(),
        ]);
    }
}
