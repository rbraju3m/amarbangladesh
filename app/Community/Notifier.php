<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\MemberNotification;

/**
 * Creates notifications where things happen. Nobody is told about their own action, and people who
 * pressed "আমারও জানা দরকার" get one unread notice per question, not one per answer.
 */
final class Notifier
{
    public static function answered(Answer $answer): void
    {
        $post = $answer->post;
        $recipients = [];
        if ($post->member_id !== $answer->member_id) {
            $recipients[$post->member_id] = 'answer';
        }

        if ($post->isQuestion()) {
            $waiting = MemberNotification::where(['post_id' => $post->id, 'type' => 'need'])->unread()->pluck('member_id')->flip();
            HelpfulMark::where(['markable_type' => 'post', 'markable_id' => $post->id])->pluck('member_id')
                ->reject(fn ($id) => $id === $answer->member_id || isset($recipients[$id]) || isset($waiting[$id]))
                ->each(function ($id) use (&$recipients) {
                    $recipients[$id] = 'need';
                });
        }

        foreach ($recipients as $memberId => $type) {
            MemberNotification::firstOrCreate(['member_id' => $memberId, 'type' => $type, 'answer_id' => $answer->id], ['post_id' => $post->id]);
        }
    }

    public static function accepted(Answer $answer): void
    {
        if ($answer->member_id !== $answer->post->member_id) {
            MemberNotification::firstOrCreate(['member_id' => $answer->member_id, 'type' => 'accepted', 'answer_id' => $answer->id], ['post_id' => $answer->post_id]);
        }
    }
}
