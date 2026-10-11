<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * "Last seen running" marks for the scheduler and the scheduled commands, kept in the cache (feature plan
 * chunk 14), read by the admin System page. Clearing the cache clears them too: the page then says
 * "no record yet" until the next run.
 */
final class Heartbeat
{
    /** name => [label, how often it should run (minutes), how late counts as a problem (minutes)] */
    public const JOBS = [
        'scheduler' => ['Scheduler (cron)', 1, 5],
        'community:send-notification-emails' => ['Notification emails', 5, 15],
        'community:prune-photos' => ['Photo clean-up', 60, 150],
        'model:prune' => ['Old analytics pruning', 1440, 1560],
    ];

    /** Mail failures kept (timestamps), enough to count a bad day. */
    private const FAILURES_KEPT = 500;

    public static function record(string $name, array $meta = []): void
    {
        try {
            Cache::forever("heartbeat.{$name}", ['at' => now()->toIso8601String()] + $meta);
        } catch (Throwable $e) {
            report($e); // a broken cache must never break the job itself
        }
    }

    /** @return ?array{at: string} plus whatever the job recorded */
    public static function get(string $name): ?array
    {
        return Cache::get("heartbeat.{$name}");
    }

    /** One notification email that couldn't be sent: only when and the exception's class (messages can hold addresses). */
    public static function mailFailed(Throwable $e): void
    {
        try {
            $list = Cache::get('heartbeat.mail-failures', []);
            $list[] = ['at' => now()->toIso8601String(), 'error' => class_basename($e)];
            Cache::forever('heartbeat.mail-failures', array_slice($list, -self::FAILURES_KEPT));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return list<array{at: string, error: string}> failures since $since, newest last */
    public static function mailFailures(\DateTimeInterface $since): array
    {
        return array_values(array_filter(Cache::get('heartbeat.mail-failures', []), fn ($f) => $f['at'] >= $since->format(DATE_ATOM)));
    }
}
