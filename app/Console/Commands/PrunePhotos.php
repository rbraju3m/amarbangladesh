<?php

namespace App\Console\Commands;

use App\Community\Photos;
use App\Support\Heartbeat;
use Illuminate\Console\Command;

class PrunePhotos extends Command
{
    protected $signature = 'community:prune-photos';

    protected $description = 'Delete photos never attached to a post (after a day) and those of removed or deleted posts (after 30 days)';

    public function handle(): int
    {
        $deleted = Photos::prune();
        Heartbeat::record($this->getName(), ['deleted' => $deleted]);
        $this->info("Deleted {$deleted} photos.");

        return self::SUCCESS;
    }
}
