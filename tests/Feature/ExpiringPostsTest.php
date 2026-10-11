<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\Member;
use App\Models\Post;
use App\Models\Report;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Lost & found and job posts (feature plan chunk 10): they leave the feed after Post::EXPIRES_DAYS, the page stays. */
class ExpiringPostsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{Member, string} */
    private function member(string $name = 'রাশেদ'): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function make(string $token, string $type, string $title): int
    {
        return $this->withHeader('X-Member-Token', $token)->postJson('/api/posts', ['type' => $type, 'title' => $title])->assertCreated()->json('post.id');
    }

    public function test_lost_and_job_posts_run_out_of_the_feed_but_keep_their_page(): void
    {
        [, $token] = $this->member();
        $lost = $this->make($token, 'lost', 'মিরপুর ১০-এ একটা কালো মানিব্যাগ হারিয়েছে');
        $job = $this->make($token, 'job', 'উত্তরায় অফিস সহকারী দরকার, বেতন আলোচনা সাপেক্ষে');
        $question = $this->make($token, 'question', 'একটা সাধারণ প্রশ্ন যেটা কখনো বন্ধ হয় না');

        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, Post::find($lost)->expires_at->timestamp, 5);
        $this->assertNull(Post::find($question)->expires_at);

        $this->get('/feed')->assertSee('মিরপুর ১০-এ একটা কালো মানিব্যাগ')->assertSee('🔎 হারানো-পাওয়া')->assertSee('📋 চাকরির খবর');
        $this->get("/p/{$job}")->assertSee('চাকরি পাইয়ে দেওয়ার নামে কেউ টাকা চাইলে দেবেন না')->assertSee('id="post-open"', false);
        $this->get("/p/{$lost}")->assertSee('লোকজনের মধ্যে দেখা করুন')->assertDontSee('চাকরি পাইয়ে দেওয়ার নামে');
        $this->get('/en/feed')->assertSee('Lost &amp; found', false)->assertSee('Job posting');

        $this->travel(31)->days();
        $this->get('/feed')->assertDontSee('মিরপুর ১০-এ একটা কালো মানিব্যাগ')->assertDontSee('উত্তরায় অফিস সহকারী')->assertSee('একটা সাধারণ প্রশ্ন যেটা কখনো বন্ধ হয় না');
        $this->get('/')->assertDontSee('মিরপুর ১০-এ একটা কালো মানিব্যাগ');
        $this->getJson('/api/feed?limit=5')->assertJsonPath('count', 1);
        $this->get("/p/{$lost}")->assertOk()->assertSee('এই পোস্টটা বন্ধ হয়ে গেছে।')->assertSee('tag-closed', false)->assertDontSee('id="post-open"', false);
        $this->get("/p/{$job}")->assertOk()->assertSee('এই চাকরির পোস্টটা বন্ধ হয়ে গেছে')->assertSee('চাকরি পাইয়ে দেওয়ার নামে'); // the warning stays
    }

    public function test_the_author_closes_one_early(): void
    {
        [, $token] = $this->member();
        [, $other] = $this->member('অন্য কেউ');
        $lost = $this->make($token, 'lost', 'ধানমন্ডি লেকে একটা চাবির গোছা পাওয়া গেছে');
        $question = $this->make($token, 'question', 'এটা প্রশ্ন, এটা বন্ধ করা যায় না');

        $this->withHeader('X-Member-Token', $other)->postJson("/api/posts/{$lost}/close")->assertForbidden();
        $this->withHeader('X-Member-Token', $token)->postJson("/api/posts/{$question}/close")->assertNotFound();
        $this->withHeader('X-Member-Token', $token)->postJson("/api/posts/{$lost}/close")->assertOk();

        $this->assertTrue(Post::find($lost)->isClosed());
        $this->assertSame('published', Post::find($lost)->status);
        $this->get('/feed')->assertDontSee('ধানমন্ডি লেকে একটা চাবির গোছা');
        $this->get("/p/{$lost}")->assertOk()->assertSee('এই পোস্টটা বন্ধ হয়ে গেছে।');
    }

    public function test_scam_is_a_report_reason(): void
    {
        [, $token] = $this->member();
        [, $reporter] = $this->member('পাঠক');
        $job = $this->make($token, 'job', 'ঘরে বসে আয়, রেজিস্ট্রেশন ফি মাত্র ৫০০ টাকা');

        $this->get("/p/{$job}")->assertSee('প্রতারণা বা টাকা চাওয়া');
        $this->withHeader('X-Member-Token', $reporter)->postJson('/api/reports', ['type' => 'post', 'id' => $job, 'reason' => 'scam'])->assertSuccessful();
        $this->assertSame('scam', Report::sole()->reason);
    }
}
