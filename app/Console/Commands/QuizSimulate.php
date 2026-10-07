<?php

namespace App\Console\Commands;

use App\Quiz\QuizConfig;
use App\Quiz\Simulator;
use Illuminate\Console\Command;

class QuizSimulate extends Command
{
    protected $signature = 'quiz:simulate';

    protected $description = 'Play every answer combination and show how often each location wins';

    public function handle(): int
    {
        $report = (new Simulator(QuizConfig::fromDatabase()))->run();

        $rows = [];
        foreach ($report['wins'] as $slug => $wins) {
            $share = $report['share'][$slug];
            $rows[] = [$slug, $wins, $share.'%', str_repeat('█', (int) round($share))];
        }
        $this->table(['Location', 'Wins', 'Share', ''], $rows);
        $this->line("Combinations: {$report['total']}  ·  Match %: min {$report['match']['min']}, max {$report['match']['max']}, avg {$report['match']['avg']}");

        return self::SUCCESS;
    }
}
