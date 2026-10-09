<?php

namespace App\Console\Commands;

use App\Community\DemoContent;
use App\Models\Member;
use Illuminate\Console\Command;

/** Removes the fictional demo members and everything they wrote (see DemoCommunitySeeder). */
class PurgeDemoCommunity extends Command
{
    protected $signature = 'community:purge-demo {--force : Skip the confirmation}';

    protected $description = 'Delete the demo community members, their posts, answers and marks';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Demo content is never seeded in production, so there is nothing to purge here.');

            return self::FAILURE;
        }

        $count = Member::where('is_demo', true)->count();
        if ($count === 0) {
            $this->components->info('No demo members.');

            return self::SUCCESS;
        }

        $this->components->warn("This deletes {$count} demo members with their posts and answers (and any real answers on demo posts).");
        if (! $this->option('force') && ! $this->confirm('Delete them?')) {
            $this->components->info('Cancelled; nothing was deleted.');

            return self::FAILURE;
        }

        [$members, $posts] = DemoContent::purge();
        $this->components->info("Deleted {$members} demo members and {$posts} posts.");

        return self::SUCCESS;
    }
}
