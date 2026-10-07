<?php

namespace App\Quiz;

use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Models\QuizResult;
use Illuminate\Support\Facades\Cache;

/**
 * Shapes a stored result for both the JSON API and the server-rendered share page,
 * so the client renders one structure regardless of how it arrived.
 */
final class ResultPresenter
{
    public static function present(QuizResult $result): array
    {
        $result->loadMissing(['location', 'secondLocation', 'referrer.location']);
        $traits = self::traitMeta();

        $friend = null;
        if ($result->referrer && $result->friend_match_pct !== null) {
            $friend = [
                'name' => $result->referrer->display_name,
                'location' => self::locationBrief($result->referrer->location),
                'pct' => $result->friend_match_pct,
                'same' => $result->referrer->location_id === $result->location_id,
            ];
        }

        return [
            'code' => $result->code,
            'url' => route('results.show', $result),
            'name' => $result->display_name,
            'location' => self::location($result->location),
            'match_pct' => $result->match_pct,
            'second' => $result->secondLocation && $result->second_location_id !== $result->location_id
                ? self::locationBrief($result->secondLocation) + ['pct' => $result->second_match_pct]
                : null,
            'traits' => collect($result->trait_scores)
                ->filter(fn ($pct, $key) => isset($traits[$key]))
                ->take(5)
                ->map(fn ($pct, $key) => $traits[$key] + ['key' => $key, 'pct' => $pct])
                ->values()->all(),
            'reason' => $result->reason_bn,
            'friend' => $friend,
        ];
    }

    public static function location(Location $location): array
    {
        return self::locationBrief($location) + [
            'name_en' => $location->name_en,
            'title_bn' => $location->title_bn,
            'tagline_bn' => $location->tagline_bn,
            'description_bn' => $location->description_bn,
            'badges' => $location->badges,
            'illustration' => $location->illustrationUrl(),
        ];
    }

    public static function locationBrief(Location $location): array
    {
        return [
            'slug' => $location->slug,
            'name_bn' => $location->name_bn,
            'emoji' => $location->emoji,
            'accent' => $location->accent_color,
        ];
    }

    /** @return array<string, array{label:string, emoji:string}> */
    private static function traitMeta(): array
    {
        return Cache::remember('quiz.trait-meta', 3600, fn () => PersonalityTrait::orderBy('sort_order')->get()
            ->mapWithKeys(fn ($t) => [$t->key => ['label' => $t->label_bn, 'emoji' => $t->emoji]])->all());
    }
}
