<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'lost' => ['label' => 'হারানো-পাওয়া', 'emoji' => '🔎'],
        'job' => ['label' => 'চাকরির খবর', 'emoji' => '📋'],
    ];

    /** Kinds that run out: days until they leave the feed (the page stays, marked closed). */
    public const EXPIRES_DAYS = ['lost' => 30, 'job' => 30];

    /** How long a help post can stay urgent, in hours (picked on the Ask page). */
    public const URGENT_HOURS = [24, 48, 72];

    /** Blood a help post can ask for. */
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';    // auto-hidden by reports or by an admin; can be restored

    public const REMOVED = 'removed';  // removed by an admin

    public const DELETED = 'deleted';  // deleted by its author

    protected $fillable = ['member_id', 'is_anonymous', 'type', 'title', 'body', 'body_html', 'category_id', 'area_id', 'status', 'accepted_answer_id', 'urgent_until', 'blood_group', 'expires_at'];

    /** The columns' defaults, so a just-created model renders its counts without a reload. */
    protected $attributes = ['answers_count' => 0, 'helpful_count' => 0, 'reports_count' => 0];

    protected function casts(): array
    {
        return ['is_anonymous' => 'boolean', 'edited_at' => 'datetime', 'urgent_until' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** The author as the public sees them: null when posted anonymously. */
    public function publicAuthor(): ?Member
    {
        return $this->is_anonymous ? null : $this->member;
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }

    /** Not run out: everything except lost & found / job posts past their `expires_at` (or closed by the author). */
    public function scopeOpen(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isClosed(): bool
    {
        return (bool) $this->expires_at?->isPast();
    }

    /** Urgent help that hasn't run out yet (nothing runs at expiry: it just stops matching). */
    public function scopeUrgent(Builder $query): void
    {
        $query->where('type', 'help')->where('urgent_until', '>', now());
    }

    public function isUrgent(): bool
    {
        return $this->type === 'help' && $this->urgent_until?->isFuture();
    }

    /** Whole hours left while urgent (at least 1), for "23 hours left". */
    public function urgentHoursLeft(): int
    {
        return max(1, (int) ceil(now()->diffInMinutes($this->urgent_until) / 60));
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function typeInfo(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES['question'];
    }

    /** The type's name in the page language (context keys, since "আলোচনা" is also the feed's name). */
    public static function typeLabel(string $type): string
    {
        return __('post.type.'.(isset(self::TYPES[$type]) ? $type : 'question'));
    }

    public function isQuestion(): bool
    {
        return in_array($this->type, ['question', 'help'], true);
    }

    public function url(): string
    {
        return lroute('posts.show', $this);
    }

    /** Re-count from the answers table; called whenever an answer appears, disappears or changes status. */
    public function refreshAnswerCount(): void
    {
        $this->answers_count = $this->answers()->published()->whereNull('parent_id')->count(); // replies aren't answers
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

    /** Its photos, in order (App\Community\Photos). */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'photoable_id')->where('photoable_type', 'post')->orderBy('position');
    }

    /** Its video, if any (App\Community\Videos). */
    public function video(): HasOne
    {
        return $this->hasOne(Video::class, 'videoable_id')->where('videoable_type', 'post');
    }
}
