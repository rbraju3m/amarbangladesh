<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Helpful marks, reports and status changes for posts and answers, in one place so counters
 * stay consistent. Reports from this many different members hide an item until an admin looks.
 */
final class Moderation
{
    public const AUTO_HIDE_REPORTS = 3;

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
            self::setStatus($item, Post::HIDDEN);
        }
    }

    /** Admin "looks fine": clear the reports and publish it again. */
    public static function dismissReports(Post|Answer $item): void
    {
        Report::where(['reportable_type' => self::typeOf($item), 'reportable_id' => $item->id])->delete();
        $item->reports_count = 0;
        self::setStatus($item, Post::PUBLISHED);
    }

    public static function setStatus(Post|Answer $item, string $status): void
    {
        $item->status = $status;
        $item->save();

        if ($item instanceof Answer) {
            $item->post->refreshAnswerCount();
            $item->thread?->refreshReplyCount();
        }
    }
}
