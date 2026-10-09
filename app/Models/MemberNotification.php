<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Someone answered your post" and friends; created by `App\Community\Notifier`. Answers that are
 * hidden, removed or deleted later drop out through `scopeVisible()` rather than by deleting rows,
 * so an answer an admin restores notifies again.
 */
class MemberNotification extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** answer: on your post · accepted: your answer solved it · need: a question you also wanted answered */
    public const TYPES = ['answer', 'accepted', 'need'];

    protected $fillable = ['member_id', 'type', 'post_id', 'answer_id', 'read_at', 'emailed_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    /** Only about answers and posts the public can still see. */
    public function scopeVisible(Builder $query): void
    {
        $query->whereHas('answer', fn ($q) => $q->published())->whereHas('post', fn ($q) => $q->published());
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(180));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
