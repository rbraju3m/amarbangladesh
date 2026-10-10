<?php

namespace App\Providers;

use App\Listeners\ProvisionSuperAdminAfterMigrations;
use App\Support\Sms\BulkSmsBd;
use App\Support\Sms\LogSms;
use App\Support\Sms\SmsSender;
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
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'bulksmsbd' => new BulkSmsBd((string) config('services.sms.bulksmsbd.api_key'), (string) config('services.sms.bulksmsbd.sender_id')),
            default => new LogSms,
        });
    }

    public function boot(): void
    {
        // The IP is only used in memory as the throttle key; it is never persisted.
        RateLimiter::for('results', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('events', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('auth', fn (Request $request) => [Limit::perMinute(10)->by('m'.$request->ip()), Limit::perHour(60)->by('h'.$request->ip())]);
        RateLimiter::for('auth-mail', fn (Request $request) => [Limit::perHour(5)->by($request->ip()), Limit::perHour(3)->by('e'.strtolower((string) $request->input('email')))]);
        RateLimiter::for('auth-sms', fn (Request $request) => [Limit::perHour(10)->by($request->ip()), Limit::perDay(5)->by('p'.preg_replace('/\D/', '', (string) $request->input('phone')))]);
        RateLimiter::for('posts', fn (Request $request) => [Limit::perMinute(3)->by('m'.$request->ip()), Limit::perHour(15)->by('h'.$request->ip())]);
        RateLimiter::for('answers', fn (Request $request) => [Limit::perMinute(6)->by('m'.$request->ip()), Limit::perHour(60)->by('h'.$request->ip())]);
        RateLimiter::for('photos', fn (Request $request) => [Limit::perMinute(20)->by('m'.$request->ip()), Limit::perHour(100)->by('h'.$request->ip())]);
        RateLimiter::for('community', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('reports', fn (Request $request) => Limit::perHour(30)->by($request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(40)->by($request->ip())); // similar questions while typing

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
