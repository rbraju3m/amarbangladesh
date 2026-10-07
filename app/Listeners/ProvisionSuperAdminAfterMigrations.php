<?php

namespace App\Listeners;

use App\Support\SuperAdmin\SuperAdminPasswordMissing;
use App\Support\SuperAdmin\SuperAdminProvisioner;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Log;

/** `php artisan migrate` (fresh clone or deploy) is where the super administrator appears. */
class ProvisionSuperAdminAfterMigrations
{
    public function handle(MigrationsEnded $event): void
    {
        if ($event->method !== 'up') {
            return;
        }

        try {
            (new SuperAdminProvisioner)->ensure();
        } catch (SuperAdminPasswordMissing $e) {
            // The migrations succeeded; say it loudly instead of failing the command.
            Log::warning($e->getMessage());
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                fwrite(STDERR, PHP_EOL.'  WARNING  '.$e->getMessage().PHP_EOL.PHP_EOL);
            }
        }
    }
}
