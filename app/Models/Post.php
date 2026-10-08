<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anything a member puts in the feed. One table for every kind (question, discussion, tip, help):
 * they share the same shape (title, optional details, category, area, answers), so a new kind is
 * one entry in TYPES, not a new table or feed.
 */
class Post extends Model
{
    public const TYPES = [
        'question' => ['label' => 'প্রশ্ন', 'emoji' => '❓'],
        'discussion' => ['label' => 'আলোচনা', 'emoji' => '💬'],
        'tip' => ['label' => 'টিপস ও তথ্য', 'emoji' => '💡'],
        'help' => ['label' => 'সাহায্য চাই', 'emoji' => '🤝'],
    ];

    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';    // auto-hidden by reports or by an admin; can be restored

    public const REMOVED = 'removed';  // removed by an admin

    public const DELETED = 'deleted';  // deleted by its author

    protected $fillable = ['member_id', 'type', 'title', 'body', 'category_id', 'area_id', 'status', 'accepted_answer_id'];

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function typeInfo(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES['question'];
    }

    public function isQuestion(): bool
    {
        return in_array($this->type, ['question', 'help'], true);
    }

    public function url(): string
    {
        return route('posts.show', $this, false);
    }

    /** Re-count from the answers table; called whenever an answer appears, disappears or changes status. */
    public function refreshAnswerCount(): void
    {
        $this->answers_count = $this->answers()->published()->count();
        if ($this->accepted_answer_id && ! $this->answers()->published()->whereKey($this->accepted_answer_id)->exists()) {
            $this->accepted_answer_id = null;
        }
        $this->save();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function acceptedAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'accepted_answer_id');
    }
}
