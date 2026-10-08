<?php

namespace App\Quiz;

/**
 * Plays every possible answer combination and reports how often each location wins.
 * Used by the admin balance checker, `quiz:simulate`, and the balance test.
 *
 * Real players don't pick answers uniformly: on a quiz they share, they favour flattering answers
 * (মেঘে ঢাকা পাহাড়, পাগলা অভিযাত্রী). With a favoured trait, the answer with the most of that trait in
 * each question is picked with probability $p and shares are weighted accordingly.
 */
final class Simulator
{
    /** The bias real players showed at launch, checked alongside the uniform case. */
    public const FAVOURED_TRAIT = 'adventure';

    public const FAVOURED_P = 0.35;

    public function __construct(private readonly QuizConfig $config) {}

    /**
     * @return array{total:int, wins:array<string,int>, share:array<string,float>, match:array{min:int,max:int,avg:float}}
     */
    public function run(?string $favour = null, float $p = self::FAVOURED_P): array
    {
        $scorer = new Scorer($this->config);
        $questions = array_values(array_map('array_values', $this->config->questions));
        $probs = array_map(fn (array $options) => $this->answerProbabilities($options, $favour, $p), $questions);
        $wins = array_fill_keys(array_keys($this->config->locations), 0.0);
        $total = 0;
        $pctMin = 100;
        $pctMax = 0;
        $pctSum = 0;
        $dims = count($this->config->traitKeys);

        $walk = function (int $depth, array $vector, array $bonus, float $weight) use (&$walk, $questions, $probs, $scorer, &$wins, &$total, &$pctMin, &$pctMax, &$pctSum) {
            if ($depth === count($questions)) {
                $ranking = $scorer->rank($vector, $bonus);
                $slug = array_key_first($ranking);
                $wins[$slug] += $weight;
                $total++;
                $t = max(0, min(1, ($ranking[$slug] - Scorer::SIM_LOW) / (Scorer::SIM_HIGH - Scorer::SIM_LOW)));
                $pct = (int) round(Scorer::MATCH_FLOOR + (Scorer::MATCH_CEIL - Scorer::MATCH_FLOOR) * $t);
                $pctMin = min($pctMin, $pct);
                $pctMax = max($pctMax, $pct);
                $pctSum += $pct;

                return;
            }
            foreach ($questions[$depth] as $o => $option) {
                $v = $vector;
                foreach ($option['weights'] as $i => $w) {
                    $v[$i] += $w;
                }
                $b = $bonus;
                foreach ($option['bonus'] as $slug => $points) {
                    $b[$slug] = ($b[$slug] ?? 0) + $points;
                }
                $walk($depth + 1, $v, $b, $weight * $probs[$depth][$o]);
            }
        };

        if ($questions) {
            $walk(0, array_fill(0, $dims, 0.0), [], 1.0);
        }

        // Weights sum to 1, so a location's weighted wins are its expected share of plays.
        $sum = array_sum($wins);

        return [
            'total' => $total,
            'wins' => array_map(fn ($w) => (int) round($sum ? $total * $w / $sum : 0), $wins),
            'share' => array_map(fn ($w) => $sum ? round(100 * $w / $sum, 2) : 0, $wins),
            'match' => ['min' => $total ? $pctMin : 0, 'max' => $pctMax, 'avg' => $total ? round($pctSum / $total, 1) : 0],
        ];
    }

    /**
     * Chance of picking each answer: uniform, or $p for the answer with the most of the favoured
     * trait (first one on a tie) when any answer in the question has it.
     *
     * @return list<float>
     */
    private function answerProbabilities(array $options, ?string $favour, float $p): array
    {
        $n = count($options);
        $trait = $favour === null ? false : array_search($favour, $this->config->traitKeys, true);
        if ($trait === false || $n < 2) {
            return array_fill(0, $n, 1 / $n);
        }

        $amounts = array_map(fn ($o) => $o['weights'][$trait] ?? 0, $options);
        if (max($amounts) <= 0) {
            return array_fill(0, $n, 1 / $n);
        }

        $probs = array_fill(0, $n, (1 - $p) / ($n - 1));
        $probs[array_search(max($amounts), $amounts, true)] = $p;

        return $probs;
    }
}
