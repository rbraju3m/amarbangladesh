<?php

namespace App\Console\Commands;

use App\Community\Videos;
use App\Support\Heartbeat;
use Illuminate\Console\Command;

class ProcessVideos extends Command
{
    protected $signature = 'community:process-videos {--seconds=240 : stop taking new videos after this long}';

    protected $description = 'Convert uploaded videos to small, metadata-free MP4s with posters (one at a time, oldest first)';

    public function handle(): int
    {
        $done = Videos::processWaiting((int) $this->option('seconds'));
        Heartbeat::record($this->getName(), $done);
        $this->info("Converted {$done['converted']}, failed {$done['failed']}, checked {$done['checked']} unchecked uploads.");

        return self::SUCCESS;
    }
}
