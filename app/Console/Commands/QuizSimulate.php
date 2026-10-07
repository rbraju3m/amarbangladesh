<?php

namespace App\Console\Commands;

use App\Quiz\QuizConfig;
use App\Quiz\Simulator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quiz:simulate')]
#[Description('Play every answer combination and show how often each location wins')]
class QuizSimulate extends Command
{
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
