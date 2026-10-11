<?php

namespace App\Models;

use App\Community\Videos;
use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A video on a post or a top-level answer (or on its way there: uploading, waiting, converting). See App\Community\Videos. */
class Video extends Model
{
    public const UPDATED_AT = null;

    public const UPLOADING = 'uploading';

    public const WAITING = 'waiting';

    public const PROCESSING = 'processing';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = [
        'member_id', 'videoable_type', 'videoable_id', 'status', 'size', 'received', 'path',
        'duration_ms', 'width', 'height', 'bytes', 'attempts', 'error', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::READY && $this->path !== null;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::UPLOADING, self::WAITING, self::PROCESSING], true);
    }

    /** "০:৪৫" (Bangla digits on Bangla pages). */
    public function length(): string
    {
        $seconds = (int) round(($this->duration_ms ?? 0) / 1000);

        return Lang::num(intdiv($seconds, 60)).':'.Lang::num(sprintf('%02d', $seconds % 60));
    }

    public function url(): ?string
    {
        return $this->isReady() ? Storage::disk('public')->url("{$this->path}.mp4") : null;
    }

    /** `webp` for pages, `jpg` for link previews. */
    public function posterUrl(string $format = 'webp'): ?string
    {
        return $this->isReady() ? Storage::disk('public')->url("{$this->path}-poster.{$format}") : null;
    }

    /** Where the pieces are put together until the file is checked and converted (private disk). */
    public function partPath(): string
    {
        return Videos::UPLOAD_DIR."/{$this->id}.part";
    }

    /** What the uploader's browser needs: how far the upload got and where conversion is. */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'received' => $this->received,
            'size' => $this->size,
            'chunk' => Videos::CHUNK_BYTES,
            'error' => $this->error,
            'duration' => $this->duration_ms ? round($this->duration_ms / 1000, 1) : null,
            'width' => $this->width,
            'height' => $this->height,
            'url' => $this->url(),
            'poster' => $this->posterUrl(),
        ];
    }
}
