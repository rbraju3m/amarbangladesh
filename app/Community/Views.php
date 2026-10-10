<?php

namespace App\Community;

use App\Models\Member;
use App\Models\Post;
use App\Support\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Post view counts. Post pages are cached and cookieless, so the browser reports a view
 * (POST /api/posts/{id}/view) with its random visitor id. A view counts once per visitor per post per
 * DAY_SECONDS: the "seen" mark is a hash of the two in the cache, never stored with the post. Not
 * counted: the author, bots, browsers without a visitor id, unpublished posts. Someone who clears their
 * browser storage counts again the same day; the number is "about how many read it", not an audit.
 */
final class Views
{
    public const DAY_SECONDS = 86400;

    /** Shown publicly from this many views (owner's decision: a small number reads as "nobody"). */
    public const SHOW_FROM = 10;

    /** @return bool whether this view was counted */
    public static function record(Post $post, ?string $visitor, ?Member $member, ?string $userAgent): bool
    {
        if (! $post->isPublished() || ! is_string($visitor) || ! Str::isUuid($visitor)
            || Device::fromUserAgent($userAgent) === 'bot' || ($member && $member->id === $post->member_id)) {
            return false;
        }
        if (! Cache::add('post-view:'.hash('sha256', "{$post->id}|{$visitor}"), 1, self::DAY_SECONDS)) {
            return false; // already counted today
        }
        Post::whereKey($post->id)->toBase()->increment('views_count'); // toBase: don't touch updated_at

        return true;
    }

    public static function shown(Post $post): bool
    {
        return $post->views_count >= self::SHOW_FROM;
    }
}
