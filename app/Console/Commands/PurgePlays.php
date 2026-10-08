<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\QuizResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wipes test/demo plays before going live or exporting the database: every quiz result and
 * analytics event. Quiz content (questions, places, traits) and admin accounts are kept.
 */
class PurgePlays extends Command
{
    protected $signature = 'quiz:purge-plays {--force : Skip the confirmation}';

    protected $description = 'Delete all quiz results and analytics events (demo data), keeping content and admins';

    public function handle(): int
    {
        $results = QuizResult::count();
        $events = AnalyticsEvent::count();

        if ($results === 0 && $events === 0) {
            $this->components->info('Nothing to delete: no plays or events.');

            return self::SUCCESS;
        }

        $this->components->warn("This deletes {$results} results and {$events} analytics events from [".DB::connection()->getDatabaseName().']. Shared result links will stop working.');
        if (! $this->option('force') && ! $this->confirm('Delete them?')) {
            $this->components->info('Cancelled; nothing was deleted.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            // Events point at results and results at referrers (both null on delete); clear events first.
            AnalyticsEvent::query()->delete();
            QuizResult::query()->whereNotNull('referrer_result_id')->update(['referrer_result_id' => null]);
            QuizResult::query()->delete();
        });
        Cache::forget('quiz.total_plays');

        $this->components->info("Deleted {$results} results and {$events} events. Content and admin accounts are untouched.");

        return self::SUCCESS;
    }
}
