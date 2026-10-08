<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Quiz\QuizConfig;
use App\Quiz\Scorer;
use App\Quiz\Simulator;
use Illuminate\View\View;

class BalanceController extends Controller
{
    /** Healthy share band for a single location, in % of all answer combinations. */
    public const MIN = 6.0;

    public const MAX = 18.0;

    /**
     * Saved scoring data for the editors' live balance preview: the active config, every place
     * (name, emoji) in sort order, the band, and which record the form is editing.
     *
     * @param  array{kind:string, id?:int|null, slug?:string}  $ctx
     */
    public static function previewData(array $ctx): array
    {
        return QuizConfig::fromDatabase()->toArray() + [
            'meta' => Location::orderBy('sort_order')->orderBy('id')->get()
                ->mapWithKeys(fn (Location $l) => [$l->slug => ['name' => $l->name_bn, 'emoji' => $l->emoji]])->all(),
            'bonusWeight' => Scorer::BONUS_WEIGHT,
            'min' => self::MIN,
            'max' => self::MAX,
            'ctx' => $ctx,
        ];
    }

    public function __invoke(): View
    {
        $config = QuizConfig::fromDatabase();
        $combinations = array_product(array_map('count', $config->questions));

        return view('admin.balance', [
            'tooMany' => $combinations > 2_000_000,
            'combinations' => $combinations,
            'report' => $combinations <= 2_000_000 ? (new Simulator($config))->run() : null,
            // The same, with players favouring the flattering answers the way real players do.
            'favoured' => $combinations <= 2_000_000 ? (new Simulator($config))->run(Simulator::FAVOURED_TRAIT) : null,
            'favouredTrait' => PersonalityTrait::where('key', Simulator::FAVOURED_TRAIT)->value('label_bn') ?? Simulator::FAVOURED_TRAIT,
            'favouredP' => (int) round(100 * Simulator::FAVOURED_P),
            'locations' => Location::orderBy('sort_order')->get()->keyBy('slug'),
            'min' => self::MIN,
            'max' => self::MAX,
        ]);
    }
}
