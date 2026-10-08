<?php

use App\Quiz\QuizConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Real players lean toward the adventurous answers, and with the launch content বান্দরবান won
 * 47% of plays. Spread "adventure" across more places and give the spontaneous answers a second
 * trait. Only rows still holding the launch values are changed, so admin edits are kept.
 */
return new class extends Migration
{
    private const PROFILES = [
        // slug => [launch, rebalanced]
        'sylhet' => [['adventure' => 5], ['adventure' => 7]],
        'bandarban' => [['social' => 5], ['social' => 7]],
        'sundarbans' => [['adventure' => 8], ['adventure' => 9]],
    ];

    private const ANSWERS = [
        // label => [launch, rebalanced]
        'ভিজতে বের হই' => [['adventure' => 3, 'nature' => 1], ['adventure' => 2, 'nature' => 1, 'water' => 1]],
        'চল! কোথায় যাচ্ছি?' => [['adventure' => 3, 'social' => 1], ['adventure' => 2, 'social' => 2]],
    ];

    public function up(): void
    {
        $this->apply(0, 1);
    }

    public function down(): void
    {
        $this->apply(1, 0);
    }

    private function apply(int $from, int $to): void
    {
        foreach (self::PROFILES as $slug => $values) {
            $row = DB::table('locations')->where('slug', $slug)->first(['id', 'profile']);
            $profile = $row ? json_decode($row->profile, true) : null;
            if (! $profile || array_intersect_assoc($values[$from], $profile) != $values[$from]) {
                continue;
            }
            DB::table('locations')->where('id', $row->id)->update(['profile' => json_encode(array_replace($profile, $values[$to]))]);
        }

        foreach (self::ANSWERS as $label => $weights) {
            foreach (DB::table('question_options')->where('label_bn', $label)->get(['id', 'trait_weights']) as $row) {
                if (self::normalize(json_decode($row->trait_weights, true)) != self::normalize($weights[$from])) {
                    continue;
                }
                DB::table('question_options')->where('id', $row->id)->update(['trait_weights' => json_encode($weights[$to])]);
            }
        }

        QuizConfig::forget();
    }

    /** Weights without zero entries, in key order, so admin-saved and seeded rows compare equal. */
    private static function normalize(?array $weights): array
    {
        $weights = array_filter($weights ?? [], fn ($w) => (float) $w != 0.0);
        ksort($weights);

        return array_map('floatval', $weights);
    }
};
