<?php

namespace App\Http\Controllers\Admin;

use App\Community\Videos;
use App\Http\Controllers\Controller;
use App\Models\MemberNotification;
use App\Models\Video;
use App\Support\Heartbeat;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/**
 * System health (feature plan chunk 14): is the scheduler running, are emails going out, is there room
 * on the disk. Counted live on every visit (all cheap). Shows names of drivers and versions only, never
 * keys, passwords or addresses.
 */
class SystemController extends Controller
{
    /** Below this much free disk, warn (photos live on this disk). */
    public const DISK_WARN_BYTES = 2 * 1024 ** 3;

    /** A video waiting longer than this for conversion means the converter isn't keeping up (or isn't running). */
    public const VIDEO_LATE_MINUTES = 20;

    public function __invoke(): View
    {
        $now = now();
        $jobs = [];
        foreach (Heartbeat::JOBS as $name => [$label, $every, $late]) {
            $beat = Heartbeat::get($name);
            $at = $beat ? Carbon::parse($beat['at']) : null;
            $jobs[$name] = [
                'label' => $label, 'every' => $every, 'at' => $at, 'meta' => $beat ? array_diff_key($beat, ['at' => 1]) : [],
                'ok' => $at && $at->gt($now->copy()->subMinutes($late)),
            ];
        }

        $since = $now->copy()->subDay();
        // One email = the notices that share an `emailed_at` (see SendNotificationEmails::emailsSince()).
        $sent = (int) MemberNotification::where('emailed_at', '>=', $since)->distinct()->count(DB::raw("CONCAT(member_id, '|', emailed_at)"));
        $failures = Heartbeat::mailFailures($since);

        $free = @disk_free_space(storage_path()) ?: null;
        $total = @disk_total_space(storage_path()) ?: null;

        $videos = $this->videos();

        $checks = [
            ['Scheduler running', $jobs['scheduler']['ok'], $jobs['scheduler']['at'] ? null : 'No run recorded yet. On the server the cron line `* * * * * php artisan schedule:run` must be set up (Forge: Scheduler).'],
            ['Photos link (storage:link)', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Run `php artisan storage:link`: photos won\'t load without it.'],
            ['Storage writable', $this->writable(), 'The web server can\'t write to storage/: uploads and logs fail.'],
            ['Debug mode off', ! config('app.debug') || app()->environment('local'), 'APP_DEBUG is on outside local: error pages show code and settings. Set APP_DEBUG=false.'],
            ['App URL set', ! str_contains((string) config('app.url'), 'localhost') || app()->environment('local'), 'APP_URL still points at localhost: share links and link previews break.'],
            ['Enough disk space', $free === null || $free > self::DISK_WARN_BYTES, 'Less than 2 GB free on the disk that holds photos and the database.'],
            ['Video converter (ffmpeg) found', $videos['ffmpeg'] !== null, 'ffmpeg/ffprobe can\'t be run, so the video button is hidden. Install it (`sudo apt install ffmpeg`) or set FFMPEG_BINARY / FFPROBE_BINARY.'],
            ['Videos converted in time', ! $videos['late'], 'A video has waited more than '.self::VIDEO_LATE_MINUTES.' minutes for conversion: check that the scheduler runs `community:process-videos` and the log.'],
            ['No email failures in 24 h', ! $failures, count($failures).' notification email(s) failed in the last 24 hours: check the mail settings and the log.'],
        ];

        return view('admin.system', [
            'jobs' => $jobs,
            'checks' => $checks,
            'mail' => ['sent' => $sent, 'failed' => count($failures), 'lastError' => end($failures) ?: null, 'driver' => config('mail.default')],
            'queue' => [
                'driver' => config('queue.default'),
                'waiting' => $this->count('jobs'),
                'failed' => $this->count('failed_jobs'),
            ],
            'database' => $this->database(),
            'videos' => $videos,
            'disk' => ['free' => $free, 'total' => $total],
            'versions' => [
                'PHP' => PHP_VERSION,
                'Laravel' => Application::VERSION,
                'MySQL' => $this->safe(fn () => DB::selectOne('SELECT VERSION() AS v')->v),
                'Environment' => app()->environment(),
                'Timezone' => config('app.timezone'),
                'Cache' => config('cache.default'),
                'OPcache' => function_exists('opcache_get_status') && (@opcache_get_status(false)['opcache_enabled'] ?? false) ? 'on' : 'off',
            ],
            'checkedAt' => $now,
        ]);
    }

    /**
     * Videos: is ffmpeg there, how many are where (uploading, waiting, converting, ready, failed), the
     * oldest one waiting, failures in the last 7 days by reason (codes only), and the space they take
     * (converted files from the `bytes` column, unfinished uploads from their part files).
     */
    private function videos(): array
    {
        $byStatus = $this->safe(fn () => Video::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->all(), []);
        $oldest = $this->safe(fn () => Video::whereIn('status', [Video::WAITING, Video::PROCESSING])->min('created_at'));
        $oldest = $oldest ? Carbon::parse($oldest) : null;
        $parts = 0;
        foreach (glob(storage_path('app/private/'.Videos::UPLOAD_DIR.'/*.part')) ?: [] as $file) {
            $parts += (int) @filesize($file);
        }

        return [
            'ffmpeg' => Videos::version(),
            'status' => array_merge(array_fill_keys([Video::UPLOADING, Video::WAITING, Video::PROCESSING, Video::READY, Video::FAILED], 0), array_map('intval', $byStatus)),
            'posted' => (int) $this->safe(fn () => Video::whereNotNull('videoable_id')->count(), 0),
            'oldest' => $oldest,
            'late' => $oldest && $oldest->lt(now()->subMinutes(self::VIDEO_LATE_MINUTES)),
            'failures' => $this->safe(fn () => Video::where('status', Video::FAILED)->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('error, COUNT(*) AS n')->groupBy('error')->pluck('n', 'error')->all(), []),
            'bytes' => (int) $this->safe(fn () => Video::where('status', Video::READY)->sum('bytes'), 0),
            'parts' => $parts,
        ];
    }

    /** Total size and the biggest tables, from MySQL's own statistics (approximate, no table scans). */
    private function database(): array
    {
        $tables = $this->safe(fn () => collect(DB::select(
            'SELECT TABLE_NAME AS name, TABLE_ROWS AS row_count, DATA_LENGTH + INDEX_LENGTH AS bytes FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY bytes DESC'
        )), collect());

        return ['bytes' => (int) $tables->sum('bytes'), 'top' => $tables->take(8)->all(), 'count' => $tables->count()];
    }

    /** Photos, video pieces (private disk) and logs all need the web server to write under storage/. */
    private function writable(): bool
    {
        $uploads = storage_path('app/private/'.Videos::UPLOAD_DIR);

        return is_writable(storage_path('app/public')) && is_writable(storage_path('app/private')) && is_writable(storage_path('logs'))
            && (! is_dir($uploads) || is_writable($uploads));
    }

    private function count(string $table): ?int
    {
        return $this->safe(fn () => Schema::hasTable($table) ? DB::table($table)->count() : null);
    }

    private function safe(callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
