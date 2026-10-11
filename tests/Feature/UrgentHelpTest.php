<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\AdminAction;
use App\Models\Area;
use App\Models\Member;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class UrgentHelpTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class); // more posts than a person makes in a minute
    }

    private const NOTE = 'আমার বাংলাদেশ এসব তথ্য যাচাই করেনি';

    /** @return array{Member, string} */
    private function member(string $name = 'রাশেদ'): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function ask(string $token, array $data)
    {
        return $this->withHeader('X-Member-Token', $token)->postJson('/api/posts', $data + ['type' => 'help']);
    }

    public function test_an_urgent_blood_request_shows_on_home_the_map_panel_and_its_page(): void
    {
        [, $token] = $this->member();
        $district = Area::where('type', 'district')->firstOrFail();
        $division = Area::findOrFail($district->parent_id);

        $id = $this->ask($token, ['title' => 'জরুরি B+ রক্ত লাগবে ঢাকা মেডিকেলে', 'urgent' => 48, 'blood_group' => 'B+', 'area' => $district->slug])
            ->assertCreated()->json('post.id');
        $post = Post::findOrFail($id);
        $this->assertSame('B+', $post->blood_group);
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $post->urgent_until->timestamp, 5);

        $this->get('/')->assertSee('জরুরি সাহায্য দরকার')->assertSee('জরুরি B+ রক্ত লাগবে ঢাকা মেডিকেলে')->assertSee('আর ৪৮ ঘণ্টা')->assertSee(self::NOTE);
        $this->get("/p/{$id}")->assertSee('id="post-urgent"', false)->assertSee('🆘 জরুরি')->assertSee('🩸 B+')->assertSee(self::NOTE);
        $this->get('/feed')->assertSee('tag-urgent', false);

        // The map's area panel gets the area's urgent help (a division covers its districts); other areas don't.
        $this->getJson("/api/feed?limit=3&compact=1&urgent=1&area={$division->slug}")->assertJsonPath('count', 1)
            ->assertJson(fn ($json) => $json->where('urgent_html', fn ($html) => str_contains($html, 'জরুরি B+ রক্ত লাগবে'))->etc());
        $other = Area::where('type', 'division')->where('id', '!=', $division->id)->firstOrFail();
        $this->getJson("/api/feed?urgent=1&area={$other->slug}")->assertJsonPath('urgent_html', '');
        $this->getJson("/api/feed?area={$division->slug}")->assertJsonPath('urgent_html', ''); // only when asked

        $this->get('/en')->assertSee('Urgent help needed')->assertSee('48 hours left');
        $this->get("/en/p/{$id}")->assertSee("Amar Bangladesh hasn't checked this");
    }

    public function test_it_stops_being_urgent_when_the_time_is_up_and_the_post_stays(): void
    {
        [, $token] = $this->member();
        $id = $this->ask($token, ['title' => 'আজ রাতেই O- রক্ত দরকার, কেউ আছেন?', 'urgent' => 24, 'blood_group' => 'O-'])->assertCreated()->json('post.id');

        $this->travel(25)->hours();
        $this->assertFalse(Post::findOrFail($id)->isUrgent());
        $this->get('/')->assertDontSee('জরুরি সাহায্য দরকার')->assertSee('আজ রাতেই O- রক্ত দরকার, কেউ আছেন?');
        $this->get("/p/{$id}")->assertOk()->assertDontSee('id="post-urgent"', false)->assertSee('🩸 O-')->assertSee(self::NOTE);

        // With the old one over, a new urgent post is fine.
        $this->ask($token, ['title' => 'আবার জরুরি সাহায্য দরকার আজকে', 'urgent' => 72])->assertCreated();
    }

    public function test_only_help_posts_one_at_a_time_and_never_anonymous(): void
    {
        [$member, $token] = $this->member();

        // Not a help post: urgency and blood are ignored.
        $id = $this->ask($token, ['type' => 'question', 'title' => 'সাধারণ একটা প্রশ্ন করছি এখানে', 'urgent' => 24, 'blood_group' => 'A+'])->assertCreated()->json('post.id');
        $this->assertNull(Post::find($id)->urgent_until);
        $this->assertNull(Post::find($id)->blood_group);

        $this->ask($token, ['title' => 'বেনামে জরুরি সাহায্য চাই এখনই', 'urgent' => 24, 'anonymous' => 1])->assertJsonValidationErrors('urgent');
        $this->ask($token, ['title' => 'জরুরি সাহায্য চাই, অনেক দরকার', 'urgent' => 12])->assertJsonValidationErrors('urgent'); // not an offered length
        $this->ask($token, ['title' => 'জরুরি সাহায্য চাই, অনেক দরকার', 'blood_group' => 'C+'])->assertJsonValidationErrors('blood_group');

        $first = $this->ask($token, ['title' => 'প্রথম জরুরি সাহায্যের পোস্ট', 'urgent' => 24])->assertCreated()->json('post.id');
        $this->ask($token, ['title' => 'প্রথম জরুরি সাহায্যের পোস্ট', 'urgent' => 24])->assertCreated()->assertJsonPath('post.id', $first); // a retry, not a second one
        $this->ask($token, ['title' => 'দ্বিতীয় জরুরি সাহায্যের পোস্ট', 'urgent' => 24])->assertJsonValidationErrors('urgent');
        $this->ask($token, ['title' => 'দ্বিতীয় সাধারণ সাহায্যের পোস্ট'])->assertCreated(); // not urgent: fine
        $this->assertSame(1, Post::urgent()->where('member_id', $member->id)->count());
    }

    public function test_the_author_ends_it_early_and_an_admin_can_clear_it_with_a_log_line(): void
    {
        [, $token] = $this->member();
        [, $otherToken] = $this->member('অন্য কেউ');
        $id = $this->ask($token, ['title' => 'জরুরি AB+ রক্ত চাই চট্টগ্রামে', 'urgent' => 72])->json('post.id');

        $this->withHeader('X-Member-Token', $otherToken)->postJson("/api/posts/{$id}/urgent-end")->assertForbidden();
        $this->withHeader('X-Member-Token', $token)->postJson("/api/posts/{$id}/urgent-end")->assertOk();
        $this->assertFalse(Post::find($id)->isUrgent());
        $this->assertSame('published', Post::find($id)->status);

        // An admin clears another one from the moderation page.
        $second = $this->ask($token, ['title' => 'আরেকটা জরুরি সাহায্যের অনুরোধ', 'urgent' => 24])->json('post.id');
        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admin/community')->assertSee('Urgent now')->assertSee('আরেকটা জরুরি সাহায্যের অনুরোধ');
        $this->actingAs($admin)->post("/admin/community/posts/{$second}/urgent-clear")->assertRedirect();
        $this->assertFalse(Post::find($second)->isUrgent());
        $log = AdminAction::where('action', 'urgent_clear')->sole();
        $this->assertSame($second, $log->target_id);
        $this->actingAs($admin)->get('/admin/community')->assertDontSee('Urgent now');
        $this->actingAs($admin)->get('/admin/community/log')->assertSee('Urgent cleared')->assertDontSee('was published');
    }
}
