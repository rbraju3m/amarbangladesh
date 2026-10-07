<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Quiz\QuizConfig;
use App\Quiz\Simulator;
use Illuminate\View\View;

class BalanceController extends Controller
{
    /** Healthy share band for a single location, in % of all answer combinations. */
    public const MIN = 6.0;

    public const MAX = 18.0;

    public function __invoke(): View
    {
        $config = QuizConfig::fromDatabase();
        $combinations = array_product(array_map('count', $config->questions));

        return view('admin.balance', [
            'tooMany' => $combinations > 2_000_000,
            'combinations' => $combinations,
            'report' => $combinations <= 2_000_000 ? (new Simulator($config))->run() : null,
            'locations' => Location::orderBy('sort_order')->get()->keyBy('slug'),
            'min' => self::MIN,
            'max' => self::MAX,
        ]);
    }
}
