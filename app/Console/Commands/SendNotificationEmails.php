<?php

namespace App\Console\Commands;

use App\Mail\AnswerNotice;
use App\Models\MemberNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails answer notifications (scheduled every 5 minutes; there is no queue worker). A notice waits
 * WAIT minutes first, so an answer that is quickly removed drops out and answers that arrive close
 * together share one email; one already seen on the site is not emailed. Limits: one email per
 * post per POST_GAP hours and DAILY a day per member; held-back notices go in a later email.
 * "আমারও জানা দরকার" notices stay on the site only.
 */
class SendNotificationEmails extends Command
{
    public const WAIT = 5;       // minutes

    public const POST_GAP = 6;   // hours between emails about the same post

    public const DAILY = 5;      // emails per member per 24 hours

    public const MAX_AGE = 48;   // hours: older unsent notices are left to the site

    protected $signature = 'community:send-notification-emails';

    protected $description = 'Email members about new answers on their posts and answers chosen as the solution';

    public function handle(): int
    {
        $pending = MemberNotification::whereIn('type', ['answer', 'accepted'])
            ->whereNull('emailed_at')->unread()->visible()
            ->whereBetween('created_at', [now()->subHours(self::MAX_AGE), now()->subMinutes(self::WAIT)])
            ->whereHas('member', fn ($q) => $q->whereNotNull('email')->where('email_notifications', true)->whereNull('blocked_at'))
            ->with(['member', 'post', 'answer.member'])
            ->orderBy('id')->limit(1000)->get();

        $sent = 0;
        foreach ($pending->groupBy('member_id') as $notices) {
            $member = $notices->first()->member;
            $budget = self::DAILY - $this->emailsSince($member->id, now()->subDay());

            foreach ($notices->groupBy('post_id') as $group) {
                if ($budget <= 0) {
                    break;
                }
                if ($this->emailsSince($member->id, now()->subHours(self::POST_GAP), $group->first()->post_id) > 0) {
                    continue;
                }

                try {
                    Mail::to($member->email)->locale($member->locale)->send(new AnswerNotice($member, $group->first()->post, $group->values()));
                } catch (Throwable $e) {
                    report($e); // left pending: the next run tries again

                    continue;
                }
                MemberNotification::whereKey($group->modelKeys())->update(['emailed_at' => now()]);
                $budget--;
                $sent++;
            }
        }

        $this->components->info("Sent {$sent} notification email(s).");

        return self::SUCCESS;
    }

    /** Emails sent: the notices of one email share their `emailed_at`. */
    private function emailsSince(int $memberId, $since, ?int $postId = null): int
    {
        return (int) MemberNotification::where('member_id', $memberId)->where('emailed_at', '>=', $since)
            ->when($postId, fn ($q) => $q->where('post_id', $postId))
            ->selectRaw('count(distinct post_id, emailed_at) as n')->value('n');
    }
}
