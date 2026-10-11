<?php

use App\Support\Heartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Heartbeats for the admin System page (App\Support\Heartbeat): the scheduler itself every minute
// (so a missing cron line shows), model:prune here since it isn't ours; our commands record their own.
Schedule::call(fn () => Heartbeat::record('scheduler'))->everyMinute()->name('heartbeat');
Schedule::command('model:prune')->daily()->onSuccess(fn () => Heartbeat::record('model:prune'));
Schedule::command('community:send-notification-emails')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('community:prune-photos')->hourly()->withoutOverlapping();
// One conversion at a time; a run that dies leaves its lock for at most 30 minutes.
Schedule::command('community:process-videos')->everyMinute()->withoutOverlapping(30);
