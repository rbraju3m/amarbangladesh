<?php

namespace App\Quiz;

/**
 * Plays every possible answer combination and reports how often each location wins.
 * Used by the admin balance checker, `quiz:simulate`, and the balance test.
 */
final class Simulator
{
    public function __construct(private readonly QuizConfig $config) {}

    /**
     * @return array{total:int, wins:array<string,int>, share:array<string,float>, match:array{min:int,max:int,avg:float}}
     */
    public function run(): array
    {
        $scorer = new Scorer($this->config);
        $questions = array_values(array_map('array_values', $this->config->questions));
        $wins = array_fill_keys(array_keys($this->config->locations), 0);
        $total = 0;
        $pctMin = 100;
        $pctMax = 0;
        $pctSum = 0;
        $dims = count($this->config->traitKeys);

        $walk = function (int $depth, array $vector, array $bonus) use (&$walk, $questions, $scorer, &$wins, &$total, &$pctMin, &$pctMax, &$pctSum) {
            if ($depth === count($questions)) {
                $ranking = $scorer->rank($vector, $bonus);
                $slug = array_key_first($ranking);
                $wins[$slug]++;
                $total++;
                $t = max(0, min(1, ($ranking[$slug] - Scorer::SIM_LOW) / (Scorer::SIM_HIGH - Scorer::SIM_LOW)));
                $pct = (int) round(Scorer::MATCH_FLOOR + (Scorer::MATCH_CEIL - Scorer::MATCH_FLOOR) * $t);
                $pctMin = min($pctMin, $pct);
                $pctMax = max($pctMax, $pct);
                $pctSum += $pct;

                return;
            }
            foreach ($questions[$depth] as $option) {
                $v = $vector;
                foreach ($option['weights'] as $i => $w) {
                    $v[$i] += $w;
                }
                $b = $bonus;
                foreach ($option['bonus'] as $slug => $points) {
                    $b[$slug] = ($b[$slug] ?? 0) + $points;
                }
                $walk($depth + 1, $v, $b);
            }
        };

        if ($questions) {
            $walk(0, array_fill(0, $dims, 0.0), []);
        }

        return [
            'total' => $total,
            'wins' => $wins,
            'share' => array_map(fn ($n) => $total ? round(100 * $n / $total, 2) : 0, $wins),
            'match' => ['min' => $total ? $pctMin : 0, 'max' => $pctMax, 'avg' => $total ? round($pctSum / $total, 1) : 0],
        ];
    }
}
