<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Post;
use App\Support\Lang;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * One email per member per post: the new answers on their post, or their answer chosen as the
 * solution. Everything that depends on the language is built in envelope()/content(), which run
 * inside the member's locale (`Mail::to()->locale()`).
 */
class AnswerNotice extends Mailable
{
    /** @param  Collection<int, MemberNotification>  $notifications  all for this member and post */
    public function __construct(public Member $member, public Post $post, public Collection $notifications) {}

    public function envelope(): Envelope
    {
        $answers = $this->notifications->where('type', 'answer');
        $first = $answers->first()?->answer;

        $subject = match (true) {
            $answers->isEmpty() => __('আপনার উত্তরটি সমাধান হিসেবে বেছে নেওয়া হয়েছে'),
            $answers->count() === 1 => __(':name আপনার পোস্টে উত্তর দিয়েছেন', ['name' => $first->publicAuthor()?->displayName() ?? __('একজন (বেনামী)')]),
            default => Lang::choice(':nটি নতুন উত্তর এসেছে', $answers->count()),
        };

        return new Envelope(subject: $subject.': “'.mb_strimwidth($this->post->title, 0, 60, '…').'”');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.answer-notice',
            text: 'mail.answer-notice-text',
            with: [
                'url' => url($this->post->url()).'#answer-'.$this->notifications->first()->answer_id,
                'settingsUrl' => url(Lang::path('/notifications')),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    /** One-click unsubscribe (RFC 8058) for mail apps that offer it. */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute(Lang::isEnglish() ? 'en.notifications.unsubscribe' : 'notifications.unsubscribe', ['member' => $this->member->code]);
    }
}
