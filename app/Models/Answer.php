<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reply to a post: an answer to a question, a comment in a discussion. Same statuses as Post.
 * With a `parent_id` it is a reply to another answer or reply; `thread_id` is then the top-level
 * answer it sits under. Top-level answers keep `replies_count` (published replies in the thread).
 */
class Answer extends Model
{
    protected $fillable = ['post_id', 'parent_id', 'thread_id', 'member_id', 'is_anonymous', 'body', 'body_html', 'status'];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean', 'edited_at' => 'datetime'];
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /** Recounts a top-level answer's published replies. */
    public function refreshReplyCount(): void
    {
        $this->forceFill(['replies_count' => static::where('thread_id', $this->id)->published()->count()])->saveQuietly();
    }

    /** The author as the public sees them: null when posted anonymously. */
    public function publicAuthor(): ?Member
    {
        return $this->is_anonymous ? null : $this->member;
    }

    /** The name the public sees: the author's, or "anonymous" (the same rule as `partials/author`). */
    public function publicName(): string
    {
        return $this->publicAuthor()?->displayName() ?? __('বেনামী');
    }

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(self::class, 'thread_id');
    }
}
