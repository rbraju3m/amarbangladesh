<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\Answer;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class ThreadTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{0: Member, 1: string} */
    private function member(string $name): array
    {
        $member = Accounts::signIn('email', strtolower($name).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    public function test_replies_form_threads_without_counting_as_answers(): void
    {
        [$asker, $askerToken] = $this->member('Asker');
        [$helper, $helperToken] = $this->member('Helper');
        [, $thirdToken] = $this->member('Third');
        $post = Post::create(['member_id' => $asker->id, 'title' => 'Which documents do I need for a passport?']);

        $answer = $this->as($helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'Your NID and the old passport.'])->assertCreated()->json('answer.id');
        $reply = $this->as($askerToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'Does a photocopy work?', 'parent' => $answer])
            ->assertCreated()->assertJson(['answer' => ['thread' => $answer], 'answers_count' => 1])->json();
        $this->assertStringContainsString('reply-card', $reply['html']);
        $deep = $this->as($thirdToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'Bring the original too.', 'parent' => $reply['answer']['id']])->json('answer.id');

        // A reply to a reply keeps what it answers but stays in the same thread.
        $this->assertSame([$reply['answer']['id'], $answer], [Answer::find($deep)->parent_id, Answer::find($deep)->thread_id]);
        $this->assertSame(2, Answer::find($answer)->replies_count);
        $this->assertSame(1, $post->fresh()->answers_count); // "unanswered" still means no answers

        // The author of what was replied to is told; nobody about their own reply.
        $this->assertTrue(MemberNotification::where(['member_id' => $helper->id, 'type' => 'reply', 'answer_id' => $reply['answer']['id']])->exists());
        $this->assertTrue(MemberNotification::where(['member_id' => $asker->id, 'type' => 'reply', 'answer_id' => $deep])->exists());
        $this->assertFalse(MemberNotification::where(['member_id' => $asker->id, 'type' => 'reply', 'answer_id' => $reply['answer']['id']])->exists());

        // Only a top-level answer can be the solution.
        $this->as($askerToken)->postJson("/api/posts/{$post->id}/accept", ['answer' => $deep])->assertStatus(422);
        $this->as($askerToken)->postJson("/api/posts/{$post->id}/accept", ['answer' => $answer])->assertJson(['accepted' => $answer]);

        $page = $this->get($post->url())->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, 'class="thread"'));
        $this->assertStringContainsString('Bring the original too.', $page);
        $this->assertStringContainsString('↪ Asker', $page); // whose words the deep reply answers

        // Replies to a removed parent still render, under a placeholder.
        $this->as($helperToken)->deleteJson("/api/answers/{$answer}")->assertNoContent();
        $this->assertSame(0, $post->fresh()->answers_count);
        $page = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('Bring the original too.', $page);
        $this->assertStringNotContainsString('Your NID and the old passport.', $page);
    }

    public function test_long_threads_show_the_first_replies_then_load_more(): void
    {
        [$asker, $askerToken] = $this->member('Asker');
        [, $helperToken] = $this->member('Helper');
        $post = Post::create(['member_id' => $asker->id, 'title' => 'Best time to visit Sajek valley?']);
        $answer = $this->as($helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'October and November.'])->json('answer.id');
        foreach (range(1, 25) as $i) {
            $this->as($i % 2 ? $askerToken : $helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => "Reply number {$i} here", 'parent' => $answer])->assertCreated();
        }

        $page = $this->get($post->url())->getContent();
        $this->assertSame(3, substr_count($page, 'class="reply-card"'));
        $this->assertStringContainsString('more: 22', $page);

        $last = Answer::where('thread_id', $answer)->orderBy('id')->skip(2)->value('id');
        $more = $this->getJson("/api/answers/{$answer}/replies?after={$last}")->assertOk()->json();
        $this->assertSame(20, substr_count($more['html'], 'class="reply-card"'));
        $this->assertTrue($more['more']);
        $this->assertSame(2, substr_count($this->getJson("/api/answers/{$answer}/replies?after={$more['last']}")->json('html'), 'class="reply-card"'));

        // From a reply notification, the whole thread opens.
        $this->assertSame(25, substr_count($this->get($post->url()."?thread={$answer}")->getContent(), 'class="reply-card"'));
    }

    public function test_authors_edit_their_posts_and_answers(): void
    {
        [$asker, $askerToken] = $this->member('Asker');
        [, $helperToken] = $this->member('Helper');
        $post = Post::create(['member_id' => $asker->id, 'title' => 'How do I renew a trade licence?']);
        $answer = $this->as($helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'At the city corporation.'])->json('answer.id');

        $this->as($helperToken)->patchJson("/api/posts/{$post->id}", ['title' => 'Someone else changing the title'])->assertForbidden();
        $this->as($askerToken)->patchJson("/api/posts/{$post->id}", ['title' => 'How do I renew a trade licence in Dhaka?', 'body' => "Line one\nLine two"])
            ->assertOk()->assertJson(['title' => 'How do I renew a trade licence in Dhaka?'])->assertJsonPath('body_html', "Line one<br>\nLine two");
        $this->assertNotNull($post->fresh()->edited_at);
        $this->as($askerToken)->patchJson("/api/posts/{$post->id}", ['title' => 'short'])->assertJsonValidationErrors('title');

        $this->as($askerToken)->patchJson("/api/answers/{$answer}", ['body' => 'Not mine to edit'])->assertForbidden();
        $html = $this->as($helperToken)->patchJson("/api/answers/{$answer}", ['body' => 'At your city corporation zone office.'])->assertOk()->json('html');
        $this->assertStringContainsString('At your city corporation zone office.', $html);
        $this->assertNotNull(Answer::find($answer)->edited_at);
        $this->get($post->url())->assertSee('in Dhaka?')->assertSee('zone office');

        // Escaped like any answer.
        $this->as($helperToken)->patchJson("/api/answers/{$answer}", ['body' => '<script>alert(1)</script> fine'])->assertOk();
        $this->get($post->url())->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_anonymous_replies_stay_anonymous_in_reply_context(): void
    {
        [$asker, $askerToken] = $this->member('Asker');
        [, $helperToken] = $this->member('Hidden');
        $post = Post::create(['member_id' => $asker->id, 'title' => 'Is the river ferry running today?']);
        $answer = $this->as($askerToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'Asking at the ghat now.'])->json('answer.id');
        $anon = $this->as($helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'It is, since morning.', 'parent' => $answer, 'anonymous' => true])->json('answer.id');
        $this->as($askerToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'Thanks!', 'parent' => $anon])->assertCreated();

        $this->get($post->url())->assertOk()->assertDontSee('Hidden');
    }
}
