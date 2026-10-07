<?php

namespace App\Quiz;

use InvalidArgumentException;

/**
 * Deterministic trait-vector matcher.
 *
 * Each chosen option adds points to trait dimensions; the user's trait vector is compared to
 * every location's profile by cosine similarity. Options may also carry a small direct bonus for
 * a "signature" location (ইলিশ → বরিশাল), worth BONUS_WEIGHT similarity per point.
 */
final class Scorer
{
    public const BONUS_WEIGHT = 0.025;

    /** Similarity range mapped onto the displayed "vibe match" percentage. */
    public const SIM_LOW = 0.70;

    public const SIM_HIGH = 1.05;

    public const MATCH_FLOOR = 72;

    public const MATCH_CEIL = 97;

    public function __construct(private readonly QuizConfig $config) {}

    /**
     * Validates that exactly one active option was chosen for every active question.
     *
     * @param  array<int|string>  $optionIds
     * @return array<int, int> question id => option id
     */
    public function resolveAnswers(array $optionIds): array
    {
        $optionIds = array_map('intval', $optionIds);
        $answers = [];

        foreach ($this->config->questions as $questionId => $options) {
            $chosen = array_values(array_intersect($optionIds, array_keys($options)));
            if (count($chosen) !== 1) {
                throw new InvalidArgumentException("Question {$questionId} needs exactly one answer.");
            }
            $answers[$questionId] = $chosen[0];
        }

        if (count($answers) !== count($optionIds)) {
            throw new InvalidArgumentException('Unknown or duplicate answers.');
        }

        return $answers;
    }

    /** @param  array<int|string>  $optionIds */
    public function score(array $optionIds): ScoreResult
    {
        $answers = $this->resolveAnswers($optionIds);
        $dims = count($this->config->traitKeys);
        $vector = array_fill(0, $dims, 0.0);
        $bonus = [];

        foreach ($answers as $questionId => $optionId) {
            $option = $this->config->questions[$questionId][$optionId];
            foreach ($option['weights'] as $i => $w) {
                $vector[$i] += $w;
            }
            foreach ($option['bonus'] as $slug => $points) {
                $bonus[$slug] = ($bonus[$slug] ?? 0) + $points;
            }
        }

        $ranking = $this->rank($vector, $bonus);
        $slugs = array_keys($ranking);
        [$top, $second] = [$slugs[0], $slugs[1] ?? $slugs[0]];

        return new ScoreResult(
            locationSlug: $top,
            matchPct: $this->displayPct($ranking[$top]),
            secondSlug: $second,
            secondPct: min($this->displayPct($ranking[$second]), $this->displayPct($ranking[$top]) - 1),
            vector: array_combine($this->config->traitKeys, $vector),
            traitScores: $this->traitScores($vector),
            reasons: $this->reasons($answers, $top),
            ranking: $ranking,
        );
    }

    /**
     * @param  list<float>  $vector
     * @param  array<string, float>  $bonus
     * @return array<string, float> slug => score, highest first; ties keep location sort order
     */
    public function rank(array $vector, array $bonus = []): array
    {
        $scores = [];
        foreach ($this->config->locations as $slug => $location) {
            $scores[$slug] = self::cosine($vector, $location['profile']) + self::BONUS_WEIGHT * ($bonus[$slug] ?? 0);
        }

        // arsort is stable since PHP 8, so equal scores keep admin sort order.
        arsort($scores);

        return $scores;
    }

    /**
     * How alike two users are, as a friendly %. Calibrated on random pairs: median pair ≈ 72 %,
     * top decile ≈ 90 %+. Sharing the same place never shows below 70 %.
     */
    public static function friendMatch(array $a, array $b, bool $samePlace = false): int
    {
        $sim = self::cosine(array_values($a), array_values($b));
        $pct = (int) round(max(20, min(99, 45 + ($sim - 0.6) / 0.35 * 50)));

        return $samePlace ? max($pct, 70) : $pct;
    }

    public static function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }

        return ($na > 0 && $nb > 0) ? $dot / sqrt($na * $nb) : 0.0;
    }

    private function displayPct(float $score): int
    {
        $t = max(0, min(1, ($score - self::SIM_LOW) / (self::SIM_HIGH - self::SIM_LOW)));

        return (int) round(self::MATCH_FLOOR + (self::MATCH_CEIL - self::MATCH_FLOOR) * $t);
    }

    /** @return array<string, int> */
    private function traitScores(array $vector): array
    {
        $max = max($vector) ?: 1;
        $scores = [];
        foreach ($this->config->traitKeys as $i => $key) {
            $scores[$key] = (int) round(38 + 60 * max(0, $vector[$i]) / $max);
        }
        arsort($scores);

        return $scores;
    }

    /**
     * How a stored play relates to every place, for the result page's "explore" sheets: the
     * display % and the (up to) two answers that pulled hardest toward each place, plus the
     * answers that fed each trait most. Tolerates content edits: options no longer active are
     * skipped, so the numbers are a live view, not part of the stored result.
     *
     * @param  list<int>  $optionIds
     * @return array{places: array<string, array{pct:int, answers:list<int>}>, traits: array<string, list<int>>}
     */
    public function explore(array $optionIds): array
    {
        $chosen = [];
        foreach ($this->config->questions as $options) {
            foreach ($optionIds as $id) {
                if (isset($options[$id])) {
                    $chosen[$id] = $options[$id];
                }
            }
        }

        $vector = array_fill(0, count($this->config->traitKeys), 0.0);
        $bonus = [];
        foreach ($chosen as $option) {
            foreach ($option['weights'] as $i => $w) {
                $vector[$i] += $w;
            }
            foreach ($option['bonus'] as $slug => $points) {
                $bonus[$slug] = ($bonus[$slug] ?? 0) + $points;
            }
        }

        $places = [];
        foreach ($this->rank($vector, $bonus) as $slug => $score) {
            $pulls = [];
            foreach ($chosen as $id => $option) {
                $pulls[$id] = $this->pull($option, $slug);
            }
            arsort($pulls);
            $places[$slug] = ['pct' => $this->displayPct($score), 'answers' => array_slice(array_keys($pulls), 0, 2)];
        }

        $traits = [];
        foreach ($this->config->traitKeys as $i => $key) {
            $weights = array_filter(array_map(fn ($o) => $o['weights'][$i] ?? 0, $chosen), fn ($w) => $w > 0);
            arsort($weights);
            $traits[$key] = array_slice(array_keys($weights), 0, 2);
        }

        return ['places' => $places, 'traits' => $traits];
    }

    /**
     * How hard one answer pulls toward a location: its trait fit, favouring the location's own
     * signature answers and discounting answers that are another place's signature.
     */
    private function pull(array $option, string $slug): float
    {
        $own = $option['bonus'][$slug] ?? 0;
        $others = array_sum($option['bonus']) - $own;

        return self::cosine($option['weights'], $this->config->locations[$slug]['profile']) + 0.5 * $own - 0.5 * $others;
    }

    /**
     * The two answers that pulled hardest toward the winning location.
     *
     * @return list<string>
     */
    private function reasons(array $answers, string $slug): array
    {
        $pulls = [];

        foreach ($answers as $questionId => $optionId) {
            $option = $this->config->questions[$questionId][$optionId];
            $pulls[$option['reason']] = $this->pull($option, $slug);
        }
        arsort($pulls);

        return array_slice(array_keys($pulls), 0, 2);
    }
}
