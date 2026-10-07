<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable(['visitor_id', 'name', 'quiz_result_id', 'referrer_result_id', 'device', 'meta'])]
class AnalyticsEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    /** Events the public client may send. Server-side events (quiz_completed) are recorded directly. */
    public const CLIENT_EVENTS = [
        'landing_view', 'share_page_view', 'quiz_started', 'question_answered', 'result_viewed',
        'share_clicked', 'card_saved', 'link_copied', 'name_added', 'retake_clicked',
    ];

    /** Raw events are kept for 180 days (`php artisan model:prune`, scheduled daily). */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(180));
    }

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
