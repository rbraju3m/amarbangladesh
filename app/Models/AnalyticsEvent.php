<?php

namespace App\Models;

use App\Support\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsEvent extends Model
{
    protected $fillable = ['visitor_id', 'name', 'quiz_result_id', 'referrer_result_id', 'device', 'meta'];

    use Prunable;

    public const UPDATED_AT = null;

    /** Events the public client may send. Server-side events (quiz_completed, post_created, answer_created, signed_up) are recorded directly. */
    public const CLIENT_EVENTS = [
        'landing_view', 'share_page_view', 'quiz_started', 'question_answered', 'result_viewed',
        'share_clicked', 'card_saved', 'link_copied', 'name_added', 'retake_clicked', 'place_opened',
        // community (post_created and answer_created are recorded server-side)
        'community_clicked', 'feed_view', 'post_view', 'ask_view', 'post_shared',
        'notifications_view', 'notification_clicked',
    ];

    /** Visitor ids come from the browser (random, in localStorage): community requests send it as a header. */
    public const VISITOR_HEADER = 'X-Visitor';

    /**
     * A server-side event (post_created, answer_created, signed_up), tied to the anonymous visitor
     * id when the browser sent one, so the quiz → community journey can be followed.
     */
    public static function server(string $name, Request $request, array $meta = [], ?string $visitor = null): self
    {
        $visitor ??= $request->header(self::VISITOR_HEADER);

        return self::create([
            'name' => $name,
            'visitor_id' => is_string($visitor) && Str::isUuid($visitor) ? $visitor : null,
            'device' => Device::fromUserAgent($request->userAgent()),
            'meta' => $meta ?: null,
        ]);
    }

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
