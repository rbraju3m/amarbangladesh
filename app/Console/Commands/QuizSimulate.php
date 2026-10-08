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
        $simulator = new Simulator(QuizConfig::fromDatabase());
        $report = $simulator->run();
        $favoured = $simulator->run(Simulator::FAVOURED_TRAIT);
        $p = (int) round(100 * Simulator::FAVOURED_P);

        $rows = [];
        foreach ($report['wins'] as $slug => $wins) {
            $share = $report['share'][$slug];
            $rows[] = [$slug, $wins, $share.'%', str_repeat('█', (int) round($share)), $favoured['share'][$slug].'%'];
        }
        $this->table(['Location', 'Wins', 'Share', '', Simulator::FAVOURED_TRAIT." {$p}%"], $rows);
        $this->line("Combinations: {$report['total']}  ·  Match %: min {$report['match']['min']}, max {$report['match']['max']}, avg {$report['match']['avg']}");
        $this->line('Last column: share when the most '.Simulator::FAVOURED_TRAIT." answer in each question is picked {$p}% of the time.");

        return self::SUCCESS;
    }
}
