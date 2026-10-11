<?php

namespace App\Console\Commands;

use App\Community\Photos;
use App\Community\Videos;
use App\Support\Heartbeat;
use Illuminate\Console\Command;

class PrunePhotos extends Command
{
    protected $signature = 'community:prune-photos';

    protected $description = 'Delete photos and videos never attached to a post (after a day) and those of removed or deleted posts (after 30 days)';

    public function handle(): int
    {
        $deleted = Photos::prune();
        $videos = Videos::prune();
        Heartbeat::record($this->getName(), ['deleted' => $deleted, 'videos' => $videos]);
        $this->info("Deleted {$deleted} photos and {$videos} videos.");

        return self::SUCCESS;
    }
}
