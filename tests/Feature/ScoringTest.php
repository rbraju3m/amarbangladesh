<?php

namespace Tests\Feature;

use App\Quiz\QuizConfig;
use App\Quiz\Scorer;
use App\Quiz\Simulator;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function firstAnswers(QuizConfig $config): array
    {
        return array_map(fn ($options) => array_key_first($options), array_values($config->questions));
    }

    public function test_scoring_is_deterministic_and_well_formed(): void
    {
        $config = QuizConfig::fromDatabase();
        $answers = $this->firstAnswers($config);

        $a = (new Scorer($config))->score($answers);
        $b = (new Scorer($config))->score(array_reverse($answers));

        $this->assertSame($a->locationSlug, $b->locationSlug);
        $this->assertSame($a->matchPct, $b->matchPct);
        $this->assertNotSame($a->locationSlug, $a->secondSlug);
        $this->assertLessThan($a->matchPct, $a->secondPct);
        $this->assertGreaterThanOrEqual(Scorer::MATCH_FLOOR, $a->matchPct);
        $this->assertLessThanOrEqual(Scorer::MATCH_CEIL, $a->matchPct);
        $this->assertCount(2, $a->reasons);
    }

    public function test_every_location_wins_a_fair_share_of_all_answer_combinations(): void
    {
        $report = (new Simulator(QuizConfig::fromDatabase()))->run();

        $this->assertSame(4 ** 8, $report['total']);
        foreach ($report['share'] as $slug => $share) {
            $this->assertGreaterThanOrEqual(6, $share, "{$slug} is too rare");
            $this->assertLessThanOrEqual(18, $share, "{$slug} is too common");
        }
    }

    public function test_no_location_dominates_when_players_favour_adventurous_answers(): void
    {
        // At launch real players leaned adventurous and বান্দরবান won 47% of plays.
        $report = (new Simulator(QuizConfig::fromDatabase()))->run(Simulator::FAVOURED_TRAIT);

        $this->assertEqualsWithDelta(100, array_sum($report['share']), 0.1);
        foreach ($report['share'] as $slug => $share) {
            $this->assertGreaterThanOrEqual(6, $share, "{$slug} is too rare for adventurous players");
            $this->assertLessThanOrEqual(18, $share, "{$slug} is too common for adventurous players");
        }
    }

    public function test_signature_answers_are_quoted_in_the_reason(): void
    {
        $config = QuizConfig::fromDatabase();
        $scorer = new Scorer($config);
        $questions = array_values(array_map('array_keys', $config->questions));
        $ilish = null;
        foreach ($config->questions as $options) {
            foreach ($options as $id => $option) {
                $ilish = $option['reason'] === 'গরম ইলিশ ভাজা' ? $id : $ilish;
            }
        }

        // Find the first combination containing ইলিশ that lands on বরিশাল.
        $found = null;
        $walk = function (int $d, array $picked) use (&$walk, &$found, $questions, $scorer, $ilish) {
            if ($found) {
                return;
            }
            if ($d === count($questions)) {
                if (in_array($ilish, $picked) && ($r = $scorer->score($picked))->locationSlug === 'barishal') {
                    $found = $r;
                }

                return;
            }
            foreach ($questions[$d] as $id) {
                $walk($d + 1, [...$picked, $id]);
            }
        };
        $walk(0, []);

        $this->assertNotNull($found);
        $this->assertContains('গরম ইলিশ ভাজা', $found->reasons);
    }

    public function test_rejects_incomplete_duplicate_or_unknown_answers(): void
    {
        $config = QuizConfig::fromDatabase();
        $scorer = new Scorer($config);
        $answers = $this->firstAnswers($config);

        foreach ([array_slice($answers, 1), [...$answers, $answers[0]], [...array_slice($answers, 1), 999999]] as $bad) {
            try {
                $scorer->score($bad);
                $this->fail('Expected rejection for '.json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_friend_match_never_looks_low_for_the_same_place(): void
    {
        $this->assertSame(99, Scorer::friendMatch([1, 2, 3], [1, 2, 3]));
        $this->assertGreaterThanOrEqual(70, Scorer::friendMatch([5, 0, 0], [0, 5, 0], samePlace: true));
        $this->assertSame(20, Scorer::friendMatch([5, 0, 0], [0, 5, 0]));
    }
}
