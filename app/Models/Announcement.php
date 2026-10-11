<?php

namespace App\Models;

use App\Models\Concerns\Bilingual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A bar under the site header on every public page (feature plan chunk 8), managed at /admin/announcements.
 * Public pages are cached (the quiz up to ~10 minutes), so a change can take that long to show everywhere.
 */
class Announcement extends Model
{
    use Bilingual;

    public const LEVELS = ['info', 'warning'];

    /** The cached list is re-read at least this often, so nothing waits on a cache flush. */
    public const CACHE_SECONDS = 600;

    private const CACHE_KEY = 'announcements.live.v1';

    protected $fillable = ['text_bn', 'text_en', 'link', 'level', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    /**
     * The one to show now: warnings first, then the most recently started. The cache holds every switched-on
     * announcement that hasn't ended (as arrays), and the time window is checked on each request, so one
     * that starts later appears on time without a flush.
     */
    public static function current(): ?self
    {
        $now = now();
        $rows = collect(Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get(['id', 'text_bn', 'text_en', 'link', 'level', 'starts_at', 'ends_at'])
            ->map(fn (self $a) => $a->only(['id', 'text_bn', 'text_en', 'link', 'level']) + [
                'starts_at' => $a->starts_at?->toIso8601String(), 'ends_at' => $a->ends_at?->toIso8601String(),
            ])->all()));

        $row = $rows
            ->filter(fn ($a) => (! $a['starts_at'] || Carbon::parse($a['starts_at'])->lte($now)) && (! $a['ends_at'] || Carbon::parse($a['ends_at'])->gt($now)))
            ->sortByDesc(fn ($a) => [$a['level'] === 'warning' ? 1 : 0, $a['starts_at'] ?? '', $a['id']])
            ->first();

        return $row ? (new self)->forceFill($row) : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Showing now, ended, waiting to start or off: for the admin list. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'off',
            $this->ends_at && $this->ends_at->lte(now()) => 'ended',
            $this->starts_at && $this->starts_at->gt(now()) => 'scheduled',
            default => 'live',
        };
    }
}
