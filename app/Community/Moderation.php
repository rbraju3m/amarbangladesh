<?php

namespace App\Community;

use App\Models\AdminAction;
use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Helpful marks, reports and status changes for posts and answers, in one place so counters
 * stay consistent. Reports from this many different members hide an item until an admin looks.
 */
final class Moderation
{
    public const AUTO_HIDE_REPORTS = 3;

    public const NEEDS_LOOK_KEY = 'admin.needs_look';

    public const TYPES = ['post' => Post::class, 'answer' => Answer::class];

    public static function find(string $type, int $id): Post|Answer|null
    {
        return isset(self::TYPES[$type]) ? self::TYPES[$type]::find($id) : null;
    }

    public static function typeOf(Post|Answer $item): string
    {
        return $item instanceof Post ? 'post' : 'answer';
    }

    /** @return array{marked: bool, count: int} */
    public static function toggleHelpful(Member $member, Post|Answer $item): array
    {
        $key = ['markable_type' => self::typeOf($item), 'markable_id' => $item->id, 'member_id' => $member->id];

        $marked = DB::transaction(function () use ($key, $item) {
            if (HelpfulMark::where($key)->delete()) {
                $item->newQuery()->whereKey($item->id)->where('helpful_count', '>', 0)->decrement('helpful_count');

                return false;
            }
            try {
                HelpfulMark::create($key);
                $item->newQuery()->whereKey($item->id)->increment('helpful_count');
            } catch (UniqueConstraintViolationException) {
                // A double tap raced us; the mark exists.
            }

            return true;
        });

        return ['marked' => $marked, 'count' => (int) $item->newQuery()->whereKey($item->id)->value('helpful_count')];
    }

    /** One report per member per item; enough distinct reports hide it. */
    public static function report(Member $member, Post|Answer $item, string $reason): void
    {
        try {
            Report::create(['member_id' => $member->id, 'reportable_type' => self::typeOf($item), 'reportable_id' => $item->id, 'reason' => $reason]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $item->increment('reports_count');
        if ($item->isPublished() && $item->reports_count >= self::AUTO_HIDE_REPORTS) {
            self::log(null, 'auto_hide', $item); // before setStatus, so the log has the status it had
            self::setStatus($item, Post::HIDDEN);
        }
        self::forgetNeedsLook();
    }

    /**
     * Items an admin should look at: hidden ones and published ones with reports (the Moderation
     * page's "Needs a look" tab), for the admin sidebar's badge. Cached for a minute.
     */
    public static function needsLookCount(): int
    {
        return Cache::remember(self::NEEDS_LOOK_KEY, 60, function () {
            $needsLook = fn ($q) => $q->where('status', Post::HIDDEN)->orWhere(fn ($q) => $q->where('status', Post::PUBLISHED)->where('reports_count', '>', 0));

            return Post::where($needsLook)->count() + Answer::where($needsLook)->count();
        });
    }

    public static function forgetNeedsLook(): void
    {
        Cache::forget(self::NEEDS_LOOK_KEY);
    }

    /**
     * Writes a line of the moderation log (App\Models\AdminAction). For a post or answer it keeps its
     * status before the action, its title or excerpt, its open reports (count, reasons, when the first
     * came in: a "keep" deletes them) and the author; for a member their name. `$user` null = automatic.
     */
    public static function log(?User $user, string $action, Post|Answer|Member $target, array $meta = []): AdminAction
    {
        if ($target instanceof Member) {
            $meta += ['name' => $target->name];
        } else {
            $reports = Report::where(['reportable_type' => self::typeOf($target), 'reportable_id' => $target->id]);
            $meta += array_filter([
                'from' => $target->getOriginal('status'),
                'title' => $target instanceof Post ? $target->title : Text::excerpt((string) $target->body, 120),
                'post_id' => $target instanceof Answer ? $target->post_id : null,
                'member_id' => $target->member_id,
                'reports' => (clone $reports)->count() ?: null,
                'reasons' => (clone $reports)->groupBy('reason')->selectRaw('reason, COUNT(*) AS n')->pluck('n', 'reason')->all() ?: null,
                'first_report_at' => (clone $reports)->min('created_at'),
            ], fn ($v) => $v !== null);
        }

        return AdminAction::create([
            'user_id' => $user?->id, 'action' => $action, 'meta' => $meta,
            'target_type' => $target instanceof Member ? 'member' : self::typeOf($target), 'target_id' => $target->id,
        ]);
    }

    /** Admin "looks fine": clear the reports and publish it again. */
    public static function dismissReports(Post|Answer $item): void
    {
        Report::where(['reportable_type' => self::typeOf($item), 'reportable_id' => $item->id])->delete();
        $item->reports_count = 0;
        self::setStatus($item, Post::PUBLISHED);
    }

    /**
     * Its author deleting an item takes its photos with it at once; after an admin removal they stay
     * Photos::REMOVED_DAYS (it may be restored) and Photos::prune() deletes them.
     */
    public static function setStatus(Post|Answer $item, string $status): void
    {
        $item->status = $status;
        $item->save();
        self::forgetNeedsLook();
        if ($status === Post::DELETED) {
            Photos::deleteFor($item);
        }

        if ($item instanceof Answer) {
            $item->post->refreshAnswerCount();
            $item->thread?->refreshReplyCount();
        }
    }
}
