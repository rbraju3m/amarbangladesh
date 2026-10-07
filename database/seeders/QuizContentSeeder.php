<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Models\Question;
use App\Quiz\QuizConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Replaces all quiz content with the V1 set in data/quiz.php. Results already
 * stored keep their location ids, so it refuses to run once results exist.
 */
class QuizContentSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('quiz_results')->exists()) {
            $this->command?->warn('Quiz results exist — skipping content reseed. Edit content from the admin panel instead.');

            return;
        }

        $data = require __DIR__.'/data/quiz.php';
        $traitKeys = array_column($data['traits'], 'key');

        DB::transaction(function () use ($data, $traitKeys) {
            Question::query()->delete();
            Location::query()->delete();
            PersonalityTrait::query()->delete();

            foreach ($data['traits'] as $i => $trait) {
                PersonalityTrait::create($trait + ['sort_order' => $i + 1]);
            }

            foreach ($data['locations'] as $i => $location) {
                [$x, $y] = $data['map'][$location['slug']];
                Location::create([
                    ...$location,
                    'profile' => array_combine($traitKeys, $location['profile']),
                    'map_x' => $x,
                    'map_y' => $y,
                    'sort_order' => $i + 1,
                ]);
            }

            foreach ($data['questions'] as $i => $q) {
                $question = Question::create([
                    'prompt_bn' => $q['prompt_bn'],
                    'kind' => $q['kind'] ?? 'emoji',
                    'sort_order' => $i + 1,
                ]);

                foreach ($q['options'] as $j => $o) {
                    $question->options()->create([
                        'emoji' => $o[0],
                        'label_bn' => $o[1],
                        'reason_bn' => $o[2],
                        'trait_weights' => $o[3],
                        'location_bonus' => $o[4] ?? [],
                        'image' => isset($o[5]) ? "images/locations/{$o[5]}.svg" : null,
                        'sort_order' => $j + 1,
                    ]);
                }
            }
        });

        QuizConfig::forget();
    }
}
