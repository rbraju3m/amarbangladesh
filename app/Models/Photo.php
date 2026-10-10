<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A photo on a post or a top-level answer (or just uploaded, not attached yet). See App\Community\Photos. */
class Photo extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['member_id', 'photoable_type', 'photoable_id', 'position', 'path', 'width', 'height', 'bytes'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function url(string $size = 'full'): string
    {
        return Storage::disk('public')->url("{$this->path}-{$size}.webp");
    }

    /** The JPEG copy for link previews (a post's first photo only; Photos::shareCopy()). */
    public function shareUrl(): string
    {
        return Storage::disk('public')->url("{$this->path}-share.jpg");
    }

    /** Size of the share copy (longest side Photos::SHARE['side']). */
    public function shareSize(): array
    {
        $scale = min(1, 1200 / max($this->width, $this->height));

        return [(int) round($this->width * $scale), (int) round($this->height * $scale)];
    }

    /** What the browser needs to show it and send it back with a post. */
    public function present(): array
    {
        return ['id' => $this->id, 'thumb' => $this->url('thumb'), 'full' => $this->url(), 'width' => $this->width, 'height' => $this->height];
    }
}
