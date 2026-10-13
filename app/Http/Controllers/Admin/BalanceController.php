<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Quiz\QuizConfig;
use App\Quiz\Replay;
use App\Quiz\Scorer;
use App\Quiz\Simulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class BalanceController extends Controller
{
    /** Healthy share band for a single location, in % of all answer combinations. */
    public const MIN = 6.0;

    public const MAX = 18.0;

    /** Simulations are kept this long; content edits change the key, so this only clears old keys. */
    public const CACHE_SECONDS = 30 * 24 * 3600;

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

    public function __invoke(Request $request): View
    {
        $config = QuizConfig::fromDatabase();
        $combinations = array_product(array_map('count', $config->questions));
        $tooMany = $combinations > 2_000_000;
        $simulated = $tooMany ? ['report' => null, 'favoured' => null, 'at' => null] : self::simulations($config, $request->boolean('fresh'));

        return view('admin.balance', [
            'tooMany' => $tooMany,
            'combinations' => $combinations,
            'report' => $simulated['report'],
            // The same, with players favouring the flattering answers the way real players do.
            'favoured' => $simulated['favoured'],
            'simulatedAt' => $simulated['at'],
            'favouredTrait' => PersonalityTrait::where('key', Simulator::FAVOURED_TRAIT)->value('label_bn') ?? Simulator::FAVOURED_TRAIT,
            'favouredP' => (int) round(100 * Simulator::FAVOURED_P),
            // Real plays keep coming in, so the replay stays live.
            'replay' => (new Replay($config))->run(),
            'trusted' => Replay::TRUSTED,
            'locations' => Location::orderBy('sort_order')->get()->keyBy('slug'),
            'min' => self::MIN,
            'max' => self::MAX,
        ]);
    }

    /**
     * Both simulations (seconds of work), cached as plain arrays under the content's fingerprint plus the
     * scoring constants: any edit to questions, answers, places or traits gives a new key, so a stale result
     * can't be shown even if a write forgets QuizConfig::forget(). `$fresh` recomputes (after a Scorer code change).
     *
     * @return array{report:array, favoured:array, at:string}
     */
    public static function simulations(QuizConfig $config, bool $fresh = false): array
    {
        $key = 'admin.balance.v1.'.md5(json_encode([
            $config->version(), Scorer::BONUS_WEIGHT, Scorer::SIM_LOW, Scorer::SIM_HIGH, Scorer::MATCH_FLOOR,
            Scorer::MATCH_CEIL, Simulator::FAVOURED_TRAIT, Simulator::FAVOURED_P,
        ]));
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, fn () => [
            'report' => (new Simulator($config))->run(),
            'favoured' => (new Simulator($config))->run(Simulator::FAVOURED_TRAIT),
            'at' => now()->toIso8601String(),
        ]);
    }
}
