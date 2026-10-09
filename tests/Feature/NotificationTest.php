<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Mail\AnswerNotice;
use App\Models\Answer;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{0: Member, 1: string} */
    private function member(string $name = 'রাশেদ'): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    private function ask(string $token, array $data = []): Post
    {
        return Post::find($this->as($token)->postJson('/api/posts', $data + ['title' => 'সিলেটে বেড়াতে কোন মাসে যাওয়া ভালো?'])->assertCreated()->json('post.id'));
    }

    private function answer(string $token, Post $post, array $data = []): Answer
    {
        return Answer::find($this->as($token)->postJson("/api/posts/{$post->id}/answers", $data + ['body' => 'অক্টোবর থেকে মার্চ, তখন বৃষ্টি কম।'])->assertCreated()->json('answer.id'));
    }

    public function test_the_asker_is_told_when_someone_else_answers(): void
    {
        [$asker, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        $post = $this->ask($askerToken);

        $this->answer($askerToken, $post, ['body' => 'আমি নিজেও একটু খুঁজছি।']);
        $this->assertSame(0, MemberNotification::count(), 'not about your own answer');

        $answer = $this->answer($helperToken, $post);
        $this->answer($helperToken, $post); // a double tap returns the same answer
        $this->assertSame(1, MemberNotification::count());
        $this->assertDatabaseHas('member_notifications', ['member_id' => $asker->id, 'type' => 'answer', 'post_id' => $post->id, 'answer_id' => $answer->id, 'read_at' => null]);
    }

    public function test_the_author_of_the_solution_is_told(): void
    {
        [, $askerToken] = $this->member();
        [$helper, $helperToken] = $this->member('নাদিয়া');
        $post = $this->ask($askerToken);
        $answer = $this->answer($helperToken, $post);

        foreach ([$answer->id, null, $answer->id] as $choice) { // choose, clear, choose again: still one notice
            $this->as($askerToken)->postJson("/api/posts/{$post->id}/accept", ['answer' => $choice])->assertOk();
        }
        $this->assertSame(1, MemberNotification::where(['member_id' => $helper->id, 'type' => 'accepted'])->count());
    }

    public function test_people_who_also_wanted_to_know_get_one_unread_notice_per_question(): void
    {
        [, $askerToken] = $this->member();
        [$curious, $curiousToken] = $this->member('তানিয়া');
        [$helper, $helperToken] = $this->member('নাদিয়া');
        $post = $this->ask($askerToken);
        foreach ([$curiousToken, $helperToken] as $token) {
            $this->as($token)->postJson('/api/helpful', ['type' => 'post', 'id' => $post->id])->assertOk();
        }

        $this->answer($helperToken, $post);
        $this->answer($helperToken, $post, ['body' => 'আর শীতে চা-বাগান সবচেয়ে সুন্দর।']);

        $this->assertSame(1, MemberNotification::where(['member_id' => $curious->id, 'type' => 'need'])->count());
        $this->assertSame(0, MemberNotification::where('member_id', $helper->id)->count(), 'the one answering is not told about it');

        // Not for discussions or tips, where the same button means "this helped".
        $tip = $this->ask($askerToken, ['type' => 'tip', 'title' => 'সিলেটে বর্ষায় গেলে ছাতা নিয়ে যাবেন']);
        $this->as($curiousToken)->postJson('/api/helpful', ['type' => 'post', 'id' => $tip->id])->assertOk();
        $this->answer($helperToken, $tip);
        $this->assertSame(0, MemberNotification::where(['member_id' => $curious->id, 'post_id' => $tip->id])->count());
    }

    public function test_notices_about_hidden_answers_drop_out_and_come_back_when_restored(): void
    {
        [$asker, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        $answer = $this->answer($helperToken, $this->ask($askerToken));

        Moderation::setStatus($answer, Post::HIDDEN);
        $this->assertSame(0, $asker->notifications()->visible()->count());

        Moderation::dismissReports($answer->fresh());
        $this->assertSame(1, $asker->notifications()->visible()->count());
    }

    public function test_the_language_and_email_are_remembered_for_notification_emails(): void
    {
        [$member, $token] = $this->member();
        $this->assertSame($member->identities()->first()->identifier, $member->email);
        $this->assertSame('bn', $member->fresh()->locale);

        $this->as($token)->withHeader('X-Locale', 'en')->postJson('/api/posts', ['title' => 'Best month to visit Sylhet?'])->assertCreated();
        $this->assertSame('en', $member->fresh()->locale);
        $this->assertArrayNotHasKey('email', $member->fresh()->toArray(), 'never serialised');
    }

    public function test_the_list_the_unread_count_and_marking_read(): void
    {
        $this->getJson('/api/notifications/unread')->assertUnauthorized();
        [, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        [, $strangerToken] = $this->member('অচেনা');
        $post = $this->ask($askerToken);
        $this->answer($helperToken, $post);
        $this->answer($helperToken, $post, ['body' => 'বেনামে বলি: শ্রীমঙ্গলে থাকুন।', 'anonymous' => true]);

        $this->as($strangerToken)->getJson('/api/notifications/unread')->assertExactJson(['count' => 0]);
        $this->as($askerToken)->getJson('/api/notifications/unread')->assertExactJson(['count' => 2]);

        $response = $this->as($askerToken)->getJson('/api/notifications')->assertOk()->assertJson(['next' => null, 'email' => ['address' => true, 'on' => true]]);
        $html = $response->json('html');
        $this->assertStringContainsString('নাদিয়া আপনার পোস্টে উত্তর দিয়েছেন', $html);
        $this->assertStringContainsString('একজন (বেনামী) আপনার পোস্টে উত্তর দিয়েছেন', $html, 'anonymous answers name no one');
        $this->assertStringContainsString("/p/{$post->id}#answer-", $html);
        $this->assertSame(2, substr_count($html, 'is-unread'));

        $this->as($askerToken)->postJson('/api/notifications/read')->assertNoContent();
        $this->as($askerToken)->getJson('/api/notifications/unread')->assertExactJson(['count' => 0]);
        $this->assertStringNotContainsString('is-unread', $this->as($askerToken)->getJson('/api/notifications')->json('html'));
        $this->assertSame('', trim($this->as($strangerToken)->getJson('/api/notifications')->json('html')));
    }

    public function test_the_list_pages_by_id(): void
    {
        [, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        $post = $this->ask($askerToken);
        foreach (range(1, 22) as $i) {
            $this->answer($helperToken, $post, ['body' => "উত্তর নম্বর {$i}"]);
        }

        $next = $this->as($askerToken)->getJson('/api/notifications')->json('next');
        $this->assertNotNull($next);
        $this->assertSame(2, substr_count($this->as($askerToken)->getJson("/api/notifications?before={$next}")->assertJson(['next' => null])->json('html'), 'notice-card'));
    }

    public function test_email_notifications_can_be_turned_off(): void
    {
        [$member, $token] = $this->member();
        $this->as($token)->patchJson('/api/notifications/settings', ['email' => false])->assertJson(['email' => ['address' => true, 'on' => false]]);
        $this->assertFalse($member->fresh()->email_notifications);
    }

    public function test_the_page_is_a_cookieless_shell(): void
    {
        foreach (['/notifications', '/en/notifications'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('notice-list', false);
            $this->assertEmpty($response->headers->getCookies(), $url);
        }
        $this->get('/en/notifications')->assertSee('No notifications yet');
    }

    private function sendEmails(): void
    {
        $this->artisan('community:send-notification-emails')->assertSuccessful();
    }

    public function test_answers_close_together_go_out_in_one_email_after_the_wait(): void
    {
        Mail::fake();
        [$asker, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        [, $otherToken] = $this->member('তানিয়া');
        $post = $this->ask($askerToken);
        $this->answer($helperToken, $post);
        $this->answer($otherToken, $post, ['body' => 'শীতে যান, চা-বাগান সবুজ থাকে।']);

        $this->sendEmails();
        Mail::assertNothingSent(); // still inside the wait

        $this->travel(6)->minutes();
        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, 1);
        Mail::assertSent(AnswerNotice::class, function (AnswerNotice $mail) use ($asker) {
            $html = $mail->render();

            return $mail->hasTo($asker->email) && $mail->locale === 'bn'
                && str_contains($mail->envelope()->subject, '২টি নতুন উত্তর এসেছে')
                && str_contains($html, 'নাদিয়া') && str_contains($html, 'চা-বাগান')
                && str_contains($html, '/notifications/unsubscribe/'.$asker->code.'?')
                && $mail->headers()->text['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });

        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, 1); // not twice
    }

    public function test_no_email_when_already_seen_removed_unsubscribed_or_on_site_only(): void
    {
        Mail::fake();
        [$asker, $askerToken] = $this->member();
        [$curious, $curiousToken] = $this->member('তানিয়া');
        [, $helperToken] = $this->member('নাদিয়া');

        $seen = $this->ask($askerToken);
        $this->answer($helperToken, $seen);
        $this->as($askerToken)->postJson('/api/notifications/read')->assertNoContent();

        $removed = $this->ask($askerToken, ['title' => 'কক্সবাজারে কম খরচে কোথায় থাকা যায়?']);
        $this->as($curiousToken)->postJson('/api/helpful', ['type' => 'post', 'id' => $removed->id])->assertOk(); // "need": site only
        Moderation::setStatus($this->answer($helperToken, $removed), Post::REMOVED);
        $this->answer($helperToken, $removed, ['body' => 'কলাতলীর ভেতরের দিকে খুঁজুন।']);
        $asker->update(['email_notifications' => false]);

        $this->travel(6)->minutes();
        $this->sendEmails();
        Mail::assertNothingSent();
        $this->assertSame(1, $curious->notifications()->count());
    }

    public function test_one_email_per_post_per_six_hours_and_five_a_day(): void
    {
        Mail::fake();
        [$asker, $askerToken] = $this->member();
        [, $helperToken] = $this->member('নাদিয়া');
        $post = $this->ask($askerToken);

        $this->answer($helperToken, $post);
        $this->travel(6)->minutes();
        $this->sendEmails();
        $this->answer($helperToken, $post, ['body' => 'আরেকটা কথা মনে পড়লো।']);
        $this->travel(6)->minutes();
        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, 1); // the second waits for the 6-hour gap

        $this->travel(6)->hours();
        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, 2);

        // A sixth post within a day waits.
        foreach (range(1, 5) as $i) {
            $this->answer($helperToken, $this->ask($askerToken, ['title' => "আরেকটা প্রশ্ন, নম্বর {$i}, জানা থাকলে বলুন"]));
        }
        $this->travel(6)->minutes();
        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, 5);
        $this->assertSame(2, $asker->notifications()->whereNull('emailed_at')->count());
    }

    public function test_emails_are_in_the_members_language(): void
    {
        Mail::fake();
        [$asker, $askerToken] = $this->member();
        [, $helperToken] = $this->member('Nadia');
        $post = $this->as($askerToken)->withHeader('X-Locale', 'en')->postJson('/api/posts', ['title' => 'Best month to visit Sylhet?'])->json('post.id');
        $this->withHeader('X-Locale', 'bn');
        $answer = $this->answer($helperToken, Post::find($post));
        $this->as($askerToken)->postJson("/api/posts/{$post}/accept", ['answer' => $answer->id])->assertOk();

        $this->travel(6)->minutes();
        $this->sendEmails();
        Mail::assertSent(AnswerNotice::class, fn (AnswerNotice $mail) => $mail->hasTo($asker->email) && $mail->locale === 'en'
            && str_contains($mail->render(), 'See the answer') && str_contains($mail->render(), '/en/notifications/unsubscribe/'));
        Mail::assertSent(AnswerNotice::class, fn (AnswerNotice $mail) => $mail->locale === 'bn' && str_contains($mail->render(), 'সমাধান হিসেবে বেছে নেওয়া হয়েছে'));
    }

    public function test_the_unsubscribe_link_asks_first_and_needs_a_valid_signature(): void
    {
        [$member] = $this->member();
        $url = URL::signedRoute('notifications.unsubscribe', ['member' => $member->code]);

        $this->get("/notifications/unsubscribe/{$member->code}")->assertForbidden();
        $this->get($url)->assertOk()->assertSee('নোটিফিকেশন ইমেইল বন্ধ করবেন?');
        $this->assertTrue($member->fresh()->email_notifications, 'opening the link (or a mail scanner) changes nothing');

        $response = $this->post($url)->assertOk()->assertSee('ইমেইল বন্ধ হয়েছে');
        $this->assertFalse($member->fresh()->email_notifications);
        $this->assertEmpty($response->headers->getCookies());
    }
}
