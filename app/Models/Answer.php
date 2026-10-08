<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reply to a post: an answer to a question, a comment in a discussion. Same statuses as Post. */
class Answer extends Model
{
    protected $fillable = ['post_id', 'member_id', 'body', 'status'];

    public function scopePublished(Builder $query): void
    {
        $query->where('status', Post::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === Post::PUBLISHED;
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
