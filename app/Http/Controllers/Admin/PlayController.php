<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\QuizResult;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Every play, newest first, filterable by period, place and how the player arrived. */
class PlayController extends Controller
{
    public const RANGES = DashboardController::RANGES + ['all' => 'All time'];

    public const PER_PAGE = 50;

    public function __invoke(Request $request): View
    {
        $days = array_key_exists($request->query('days'), self::RANGES) ? $request->query('days') : 'all';
        $place = $request->integer('place') ?: null;
        $source = in_array($request->query('source'), ['direct', 'friend'], true) ? $request->query('source') : null;

        $plays = QuizResult::query()
            ->with(['location', 'secondLocation', 'referrer:id,code,display_name'])
            ->when($days !== 'all', fn ($q) => $q->where('created_at', '>=', $days === '1' ? now()->startOfDay() : now()->subDays((int) $days)))
            ->when($place, fn ($q) => $q->where('location_id', $place))
            ->when($source === 'direct', fn ($q) => $q->whereNull('referrer_result_id'))
            ->when($source === 'friend', fn ($q) => $q->whereNotNull('referrer_result_id'))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.plays', [
            'plays' => $plays,
            'ranges' => self::RANGES,
            'days' => $days,
            'place' => $place,
            'source' => $source,
            'locations' => Location::orderBy('sort_order')->get(['id', 'name_bn', 'emoji']),
        ]);
    }
}
