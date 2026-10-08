<?php

namespace App\Quiz;

use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Models\QuizResult;
use App\Support\Lang;
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
        $explore = (new Scorer(QuizConfig::load()))->explore($result->answer_ids ?? []);

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
            'url' => lroute('results.show', $result, true),
            'name' => $result->display_name,
            'location' => self::location($result->location),
            'match_pct' => $result->match_pct,
            'second' => $result->secondLocation && $result->second_location_id !== $result->location_id
                ? self::locationBrief($result->secondLocation) + ['pct' => $result->second_match_pct]
                : null,
            // Sort here: MySQL JSON columns don't keep key order, so the stored ranking is lost.
            'traits' => collect($result->trait_scores)
                ->filter(fn ($pct, $key) => isset($traits[$key]))
                ->sortDesc()
                ->take(5)
                ->map(fn ($pct, $key) => $traits[$key] + ['key' => $key, 'pct' => $pct, 'answers' => $explore['traits'][$key] ?? []])
                ->values()->all(),
            'places' => self::places($result, $explore['places']),
            'reason' => self::reason($result),
            'friend' => $friend,
        ];
    }

    /** Text fields are in the page language (Bangla where no English is written yet). */
    public static function location(Location $location): array
    {
        return self::locationBrief($location) + [
            'name_en' => $location->name_en,
            'title' => $location->text('title'),
            'tagline' => $location->text('tagline'),
            'description' => $location->text('description'),
            'badges' => $location->localizedBadges(),
            'illustration' => $location->illustrationUrl(),
        ];
    }

    /** "the call of the hills", "hot fried hilsa" + tail → "The call of the hills and hot fried hilsa — tail". */
    public static function englishReason(array $reasons, string $tail): string
    {
        return ucfirst(implode(' and ', $reasons)).' — '.$tail;
    }

    /** Why this place, in the page language. Older results stored only Bangla; English rebuilds it. */
    private static function reason(QuizResult $result): string
    {
        if (! Lang::isEnglish()) {
            return $result->reason_bn;
        }
        if ($result->reason_en) {
            return $result->reason_en;
        }
        $reasons = (new Scorer(QuizConfig::load()))->englishReasons($result->answer_ids ?? [], $result->location->slug);

        return $reasons ? self::englishReason($reasons, $result->location->text('reason_tail')) : $result->reason_bn;
    }

    /**
     * Every place with this player's match % and the answers that pulled toward it, best first.
     * The stored winner and runner-up keep their stored %, and live numbers for the rest are
     * capped below them, so editing content never reorders what a shared result shows.
     *
     * @param  array<string, array{pct:int, answers:list<int>}>  $live
     * @return list<array{slug:string, pct:int, answers:list<int>}>
     */
    private static function places(QuizResult $result, array $live): array
    {
        $top = $result->location->slug;
        $second = $result->secondLocation?->slug;
        $cap = ($result->second_match_pct ?? $result->match_pct) - 1;

        $places = [];
        foreach ($live as $slug => $place) {
            $pct = match ($slug) {
                $top => $result->match_pct,
                $second => $result->second_match_pct,
                default => min($place['pct'], $cap),
            };
            $places[] = ['slug' => $slug, 'pct' => $pct, 'answers' => $place['answers']];
        }
        usort($places, fn ($a, $b) => [$b['slug'] === $top, $b['pct']] <=> [$a['slug'] === $top, $a['pct']]);

        return $places;
    }

    public static function locationBrief(Location $location): array
    {
        return [
            'slug' => $location->slug,
            'name' => Lang::isEnglish() && $location->name_en ? $location->name_en : $location->name_bn,
            'emoji' => $location->emoji,
            'accent' => $location->accent_color,
        ];
    }

    /** @return array<string, array{label:string, emoji:string}> */
    private static function traitMeta(): array
    {
        return Cache::remember('quiz.trait-meta.'.Lang::current(), 3600, fn () => PersonalityTrait::orderBy('sort_order')->get()
            ->mapWithKeys(fn ($t) => [$t->key => ['label' => $t->text('label'), 'emoji' => $t->emoji]])->all());
    }
}
