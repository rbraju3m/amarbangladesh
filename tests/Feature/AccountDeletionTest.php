<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Post;
use App\Models\Report;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    /** @return array{0: string, 1: Member} */
    private function account(string $email, string $name = 'মিতু'): array
    {
        $token = $this->postJson('/api/auth/email/register', ['name' => $name, 'email' => $email, 'password' => 'secret-pass'])->assertCreated()->json('token');

        return [$token, Member::where('email', $email)->firstOrFail()];
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    public function test_deleting_keeps_content_under_a_deleted_author_and_forgets_the_rest(): void
    {
        [$mine, $me] = $this->account('mitu@example.com');
        [$other, $them] = $this->account('rafi@example.com', 'রাফি');

        $post = Post::findOrFail($this->as($mine)->postJson('/api/posts', ['title' => 'বান্দরবানে বর্ষায় যাওয়া কি নিরাপদ?'])->assertCreated()->json('post.id'));
        $theirs = Post::findOrFail($this->as($other)->postJson('/api/posts', ['title' => 'সিলেটে চা বাগান কোনটা ভালো?'])->assertCreated()->json('post.id'));
        $this->as($other)->postJson("/api/posts/{$post->id}/answers", ['body' => 'শুকনো মৌসুমে যাওয়াই ভালো।'])->assertCreated();
        $this->as($mine)->postJson("/api/posts/{$theirs->id}/answers", ['body' => 'মালনীছড়া দেখতে পারেন।'])->assertCreated();
        $this->as($mine)->postJson('/api/helpful', ['type' => 'post', 'id' => $theirs->id])->assertOk();
        $this->as($mine)->postJson('/api/reports', ['type' => 'post', 'id' => $theirs->id, 'reason' => array_key_first(Report::REASONS)])->assertSuccessful();
        $this->assertSame(1, $theirs->fresh()->helpful_count);
        $this->assertTrue(MemberNotification::where('member_id', $me->id)->exists());
        $oldCode = $me->code;

        $this->as($mine)->deleteJson('/api/members/me', ['confirm' => 'wrong'])->assertJsonValidationErrors('confirm');
        $this->as($mine)->deleteJson('/api/members/me', ['confirm' => $oldCode])->assertNoContent();

        $me->refresh();
        $this->assertTrue($me->isDeleted());
        $this->assertNull($me->email);
        $this->assertNull($me->name);
        $this->assertFalse($me->identities()->exists());
        $this->assertFalse(MemberNotification::where('member_id', $me->id)->exists());
        $this->assertSame(0, $theirs->fresh()->helpful_count);
        $this->assertSame(0, $theirs->fresh()->reports_count);

        // Signed out everywhere; the old profile is gone; the content stays with a "deleted account" author.
        $this->as($mine)->getJson('/api/members/me')->assertUnauthorized();
        $this->get("/u/{$oldCode}")->assertNotFound();
        $this->get("/u/{$me->code}")->assertNotFound();
        $this->get($post->url())->assertOk()->assertSee('মুছে ফেলা অ্যাকাউন্ট')->assertDontSee("/u/{$oldCode}");
        $this->assertSame(Post::PUBLISHED, $post->fresh()->status);

        // An answer to a post they left up tells nobody; signing in again with the same email starts afresh.
        $this->as($other)->postJson("/api/posts/{$post->id}/answers", ['body' => 'আরেকটা উত্তর দিলাম।'])->assertCreated();
        $this->assertFalse(MemberNotification::where('member_id', $me->id)->exists());
        $this->postJson('/api/auth/email/login', ['email' => 'mitu@example.com', 'password' => 'secret-pass'])->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/email/register', ['name' => 'মিতু', 'email' => 'mitu@example.com', 'password' => 'secret-pass'])->assertCreated();
        $this->assertNotSame($me->id, Member::where('email', 'mitu@example.com')->value('id'));
    }

    public function test_deleting_with_content_removes_posts_and_answers(): void
    {
        [$mine, $me] = $this->account('mitu@example.com');
        [$other] = $this->account('rafi@example.com', 'রাফি');

        $post = Post::findOrFail($this->as($mine)->postJson('/api/posts', ['title' => 'কক্সবাজারে কোন মাসে ভিড় কম?'])->json('post.id'));
        $theirs = Post::findOrFail($this->as($other)->postJson('/api/posts', ['title' => 'সুন্দরবনে কীভাবে যাবো?'])->json('post.id'));
        $answerId = $this->as($mine)->postJson("/api/posts/{$theirs->id}/answers", ['body' => 'খুলনা থেকে লঞ্চে।'])->assertCreated()->json('answer.id');
        $this->assertSame(1, $theirs->fresh()->answers_count);

        $this->as($mine)->deleteJson('/api/members/me', ['confirm' => $me->code, 'content' => true])->assertNoContent();

        $this->assertSame(Post::DELETED, $post->fresh()->status);
        $this->assertSame(Post::DELETED, Answer::findOrFail($answerId)->status);
        $this->assertSame(0, $theirs->fresh()->answers_count);
        $this->get($post->url())->assertNotFound();
    }

    public function test_signed_out_and_device_only_members_cannot_delete(): void
    {
        $this->deleteJson('/api/members/me', ['confirm' => 'x'])->assertUnauthorized();
    }
}
