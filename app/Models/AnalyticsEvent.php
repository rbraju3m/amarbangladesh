<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class AnalyticsEvent extends Model
{
    protected $fillable = ['visitor_id', 'name', 'quiz_result_id', 'referrer_result_id', 'device', 'meta'];

    use Prunable;

    public const UPDATED_AT = null;

    /** Events the public client may send. Server-side events (quiz_completed, post_created, answer_created) are recorded directly. */
    public const CLIENT_EVENTS = [
        'landing_view', 'share_page_view', 'quiz_started', 'question_answered', 'result_viewed',
        'share_clicked', 'card_saved', 'link_copied', 'name_added', 'retake_clicked', 'place_opened',
        // community (post_created and answer_created are recorded server-side)
        'community_clicked', 'feed_view', 'post_view', 'ask_view', 'post_shared',
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
