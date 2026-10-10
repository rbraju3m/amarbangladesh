<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the moderation log (append-only: rows can't be changed or deleted through the model).
 * Written by App\Community\Moderation::log(); shown on /admin/community/log.
 */
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    /** action => [label, colour class] for the log page */
    public const ACTIONS = [
        'hide' => ['Hidden', 'bg-warn/10 text-warn'],
        'auto_hide' => ['Hidden by reports', 'bg-warn/10 text-warn'],
        'restore' => ['Restored', 'bg-flag-green/10 text-green-text'],
        'dismiss' => ['Kept, reports cleared', 'bg-flag-green/10 text-green-text'],
        'remove' => ['Removed', 'bg-danger/10 text-danger'],
        'block' => ['Member blocked', 'bg-danger/10 text-danger'],
        'unblock' => ['Member unblocked', 'bg-paper-2 text-ink'],
    ];

    protected $fillable = ['user_id', 'action', 'target_type', 'target_id', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The moderation log is append-only.'));
        static::deleting(fn () => throw new LogicException('The moderation log is append-only.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
