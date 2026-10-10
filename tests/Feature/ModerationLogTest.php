<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Models\AdminAction;
use App\Models\Answer;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ModerationLogTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function reporters(int $n): array
    {
        return array_map(fn () => Accounts::signIn('email', Str::random(8).'@example.test', 'R'), range(1, $n));
    }

    public function test_every_admin_action_is_logged_with_what_it_erases(): void
    {
        $admin = User::factory()->create(['name' => 'Mod One']);
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        $post = Post::create(['member_id' => $author->id, 'title' => 'Spammy post']);
        [$a, $b] = $this->reporters(2);
        Moderation::report($a, $post, 'spam');
        Moderation::report($b, $post, 'abuse');

        // Keep: the reports are deleted, the log keeps them (count, reasons, when the first came in).
        $this->actingAs($admin)->post("/admin/community/post/{$post->id}/dismiss")->assertRedirect();
        $this->assertSame(0, Report::count());
        $kept = AdminAction::latest('id')->first();
        $this->assertSame(['dismiss', 'post', $post->id, $admin->id], [$kept->action, $kept->target_type, $kept->target_id, $kept->user_id]);
        $this->assertSame(2, $kept->meta['reports']);
        $this->assertEquals(['abuse' => 1, 'spam' => 1], $kept->meta['reasons']);
        $this->assertArrayHasKey('first_report_at', $kept->meta);
        $this->assertSame('Spammy post', $kept->meta['title']);
        $this->assertSame('published', $kept->meta['from']);

        $this->actingAs($admin)->post("/admin/community/post/{$post->id}/remove");
        $this->actingAs($admin)->post("/admin/community/post/{$post->id}/restore");
        $this->assertSame('removed', AdminAction::latest('id')->first()->meta['from']);
        $answer = Answer::create(['post_id' => $post->id, 'member_id' => $author->id, 'body' => 'An answer to hide']);
        $this->actingAs($admin)->post("/admin/community/answer/{$answer->id}/hide");
        $this->assertSame($post->id, AdminAction::latest('id')->first()->meta['post_id']);
        $this->actingAs($admin)->post("/admin/community/members/{$author->id}/block");
        $this->actingAs($admin)->post("/admin/community/members/{$author->id}/unblock");

        $this->assertSame(['dismiss', 'remove', 'restore', 'hide', 'block', 'unblock'], AdminAction::orderBy('id')->pluck('action')->all());
        $this->assertSame(['Author'], AdminAction::where('target_type', 'member')->get()->pluck('meta.name')->unique()->values()->all());

        // The log page shows them; the filter narrows it.
        $this->actingAs($admin)->get('/admin/community/log')->assertOk()
            ->assertSee('Kept, reports cleared')->assertSee('Spammy post')->assertSee('2 reports')->assertSee('abuse, spam')->assertSee('first report')->assertSee('Mod One')
            ->assertSee('Member blocked')->assertSee('An answer to hide')->assertSee('#answer-'.$answer->id, false);
        $this->actingAs($admin)->get('/admin/community/log?action=block')->assertOk()->assertSee('Member blocked')->assertDontSee('Spammy post');
        $this->get('/admin/community/log')->assertOk(); // still signed in
        auth()->logout();
        $this->get('/admin/community/log')->assertRedirect();
    }

    public function test_automatic_hides_are_logged_and_the_log_is_append_only(): void
    {
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        $post = Post::create(['member_id' => $author->id, 'title' => 'Reported thrice']);
        foreach ($this->reporters(Moderation::AUTO_HIDE_REPORTS) as $reporter) {
            Moderation::report($reporter, $post->fresh(), 'spam');
        }

        $row = AdminAction::sole();
        $this->assertSame(['auto_hide', null, Moderation::AUTO_HIDE_REPORTS], [$row->action, $row->user_id, $row->meta['reports']]);
        $this->assertSame('hidden', $post->fresh()->status);

        $this->expectException(LogicException::class);
        $row->update(['action' => 'restore']);
    }

    public function test_the_sidebar_shows_how_many_need_a_look(): void
    {
        $admin = User::factory()->create();
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        Post::create(['member_id' => $author->id, 'title' => 'Fine']);
        $hidden = Post::create(['member_id' => $author->id, 'title' => 'Hidden', 'status' => Post::HIDDEN]);
        $reported = Post::create(['member_id' => $author->id, 'title' => 'Reported']);
        Moderation::report($this->reporters(1)[0], $reported, 'false');
        Moderation::forgetNeedsLook();

        $this->assertSame(2, Moderation::needsLookCount());
        $this->actingAs($admin)->get('/admin/community')->assertSee('<span class="nav-badge" title="2 need a look">', false);

        // Acting on one updates the badge at once (the cache is cleared).
        $this->actingAs($admin)->post("/admin/community/post/{$hidden->id}/remove");
        $this->assertSame(1, Moderation::needsLookCount());
        $this->actingAs($admin)->post("/admin/community/post/{$reported->id}/dismiss");
        $this->actingAs($admin)->get('/admin/community')->assertDontSee('class="nav-badge"', false);
    }
}
