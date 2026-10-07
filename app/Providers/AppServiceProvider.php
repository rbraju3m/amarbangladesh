<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The IP is only used in memory as the throttle key; it is never persisted.
        RateLimiter::for('results', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
