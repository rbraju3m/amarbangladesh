<?php

namespace App\Providers;

use App\Listeners\ProvisionSuperAdminAfterMigrations;
use App\Support\SuperAdmin\SuperAdminProvisioner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

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

        Event::listen(MigrationsEnded::class, ProvisionSuperAdminAfterMigrations::class);
        $this->autoProvisionSuperAdmin();
    }

    /**
     * "The app ran" is enough for the super administrator to exist: checked on boot at most once
     * per interval (a single cache lookup in between). Never breaks a request.
     */
    private function autoProvisionSuperAdmin(): void
    {
        if (! config('admin.super_admin.auto_provision') || $this->app->runningUnitTests()) {
            return;
        }

        try {
            if (Cache::add('admin:super-admin:checked', true, (int) config('admin.super_admin.auto_provision_interval', 3600))) {
                (new SuperAdminProvisioner)->ensure();
            }
        } catch (Throwable $e) {
            Log::warning('Super-admin auto-provision skipped: '.$e->getMessage());
        }
    }
}
