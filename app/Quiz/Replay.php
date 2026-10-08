<?php

namespace App\Quiz;

use App\Models\Location;
use App\Models\QuizResult;
use InvalidArgumentException;

/**
 * Re-scores real players' stored answers with the current content, so the admin can see how
 * actual players would be spread before (and after) editing weights. Unlike the Simulator,
 * this reflects which answers people really pick.
 *
 * One play per person (their latest), so someone replaying ten times counts once. Plays whose
 * answers no longer fit the active questions (a question or answer was added or switched off) are
 * skipped.
 */
final class Replay
{
    /** Most recent players replayed; enough for stable shares, cheap enough for a page load. */
    public const LIMIT = 5000;

    /** Below this many players the shares are mostly noise. */
    public const TRUSTED = 100;

    public function __construct(private readonly QuizConfig $config) {}

    /**
     * @return array{players:int, replayed:int, skipped:int, changed:int, then:array<string,float>, now:array<string,float>, favoured_rate:?float, random_rate:?float}
     */
    public function run(): array
    {
        $latestPerVisitor = QuizResult::whereNotNull('visitor_id')->selectRaw('MAX(id)')->groupBy('visitor_id');
        $plays = QuizResult::where(fn ($q) => $q->whereIn('id', $latestPerVisitor)->orWhereNull('visitor_id'))
            ->latest('id')->limit(self::LIMIT)->get(['id', 'location_id', 'answer_ids']);

        $scorer = new Scorer($this->config);
        $favoured = (new Simulator($this->config))->favouredOptions(Simulator::FAVOURED_TRAIT);
        $slugById = Location::pluck('slug', 'id');
        $then = $now = array_fill_keys(array_keys($this->config->locations), 0);
        $skipped = $changed = $favouredPicks = 0;

        foreach ($plays as $play) {
            try {
                $slug = $scorer->score($play->answer_ids ?? [])->locationSlug;
            } catch (InvalidArgumentException) {
                $skipped++;

                continue;
            }
            $now[$slug]++;
            $was = $slugById[$play->location_id] ?? null;
            if (isset($then[$was])) {
                $then[$was]++;
            }
            if ($was !== $slug) {
                $changed++;
            }
            $favouredPicks += count(array_intersect($play->answer_ids, $favoured));
        }

        $replayed = $plays->count() - $skipped;
        $share = fn (array $counts) => array_map(fn ($n) => $replayed ? round(100 * $n / $replayed, 1) : 0.0, $counts);

        return [
            'players' => $plays->count(),
            'replayed' => $replayed,
            'skipped' => $skipped,
            'changed' => $changed,
            'then' => $share($then),
            'now' => $share($now),
            // How often players picked the most adventurous answer, vs the Simulator's assumption.
            'favoured_rate' => $replayed && $favoured ? round(100 * $favouredPicks / ($replayed * count($favoured)), 1) : null,
            // The same rate if everyone picked at random.
            'random_rate' => $favoured ? round(100 * array_sum(array_map(fn ($q) => 1 / count($this->config->questions[$q]), array_keys($favoured))) / count($favoured), 1) : null,
        ];
    }
}
