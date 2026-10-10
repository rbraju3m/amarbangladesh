<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Community\Taxonomy;
use App\Models\Answer;
use App\Models\Area;
use App\Models\Member;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    /** A signed-in member and their browser token. @return array{0: Member, 1: string} */
    private function member(?string $name = 'রাশেদ'): array
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
        $id = $this->as($token)->postJson('/api/posts', $data + ['title' => 'ঢাকায় ভালো আর সাশ্রয়ী ডেন্টিস্ট কোথায় পাবো?'])
            ->assertCreated()->json('post.id');

        return Post::find($id);
    }

    public function test_reference_data_is_migrated(): void
    {
        $this->assertSame(8, Area::where('type', 'division')->count());
        $this->assertSame(64, Area::where('type', 'district')->count());
        $this->assertSame('সিলেট', Area::where('slug', 'sylhet')->first()->parent->name_bn);
    }

    public function test_community_pages_are_cookieless_and_have_empty_states(): void
    {
        foreach (['/feed', '/ask', '/me', '/feed?tab=unanswered&category=health&area=sylhet-division'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertEmpty($response->headers->getCookies(), $url);
            $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        }
        $this->get('/feed')->assertSee('আলোচনা সবে শুরু হচ্ছে');
        $this->get('/feed?category=health')->assertSee('এখানে এখনো কোনো পোস্ট নেই');
    }

    public function test_home_is_the_community_and_the_quiz_has_its_own_page(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $areas = Taxonomy::areas();
        Post::create(['member_id' => $member->id, 'title' => 'Where to renew a passport in Sylhet?', 'area_id' => $areas['sylhet']['id']]);
        Post::create(['member_id' => $member->id, 'title' => 'Best tea garden near Sreemangal?', 'area_id' => $areas['moulvibazar']['id']]);

        $html = $this->get('/')->assertOk()->assertHeader('Cache-Control')->getContent();
        $this->assertSame(8, substr_count($html, 'class="division" data-level='), 'the eight divisions are drawn');
        $this->assertStringContainsString('Where to renew a passport', $html);
        $this->assertStringContainsString('href="/ask"', $html);
        $this->assertStringContainsString('href="/quiz"', $html);
        $this->assertMatchesRegularExpression('~class="map-name"[^>]*>\s*<span class="map-live" x-show="!topic"><span class="map-ping"></span></span>সিলেট\s*</span>~u', $html); // names on the map, a pulse for this week
        $this->assertStringContainsString('aria-label="সিলেট বিভাগ: ২টি আলোচনা"', $html); // Sylhet's two posts, districts included
        $this->assertStringNotContainsString('আমার বাংলাদেশ খুঁজে দেখি', $html);

        $quiz = $this->get('/quiz')->assertOk()->getContent();
        $this->assertStringContainsString('আমার বাংলাদেশ খুঁজে দেখি', $quiz);
        $this->assertStringNotContainsString('community-title', $quiz);
        $this->get('/en')->assertOk()->assertSee('href="/en/quiz"', false);
    }

    public function test_writing_needs_a_signed_in_account(): void
    {
        $this->postJson('/api/posts', ['title' => 'কোনো টোকেন ছাড়া প্রশ্ন করা যায়?'])->assertUnauthorized()->assertJson(['login' => true]);
        $this->withHeader('X-Member-Token', str_repeat('x', 40))->postJson('/api/posts', ['title' => 'ভুল টোকেন দিয়ে প্রশ্ন করা যায়?'])->assertUnauthorized();

        // A device-only member from before sign-in existed can read its identity but not write.
        $legacy = Member::create(['code' => 'legacy01', 'name' => 'পুরনো']);
        $token = $legacy->issueToken();
        $this->as($token)->getJson('/api/members/me')->assertOk()->assertJson(['member' => ['account' => false]]);
        $this->as($token)->postJson('/api/posts', ['title' => 'লগইন ছাড়া পুরনো পরিচয়ে প্রশ্ন?'])->assertUnauthorized();
        $this->as($token)->postJson('/api/helpful', ['type' => 'post', 'id' => 1])->assertUnauthorized();
    }

    public function test_a_member_asks_with_optional_category_and_area_and_it_shows_in_the_feed(): void
    {
        [$member, $token] = $this->member();
        $post = $this->ask($token, ['category' => 'health', 'area' => 'dhaka', 'body' => "<b>বাজেট</b> ২০০০ টাকা।\n\n\n\nমিরপুরে হলে ভালো।"]);

        $this->assertSame($member->id, $post->member_id);
        $this->assertSame('question', $post->type);
        $this->assertSame("বাজেট ২০০০ টাকা।\n\nমিরপুরে হলে ভালো।", $post->body, 'tags stripped, blank lines collapsed');
        $this->assertSame('dhaka', $post->area->slug);

        $this->get('/feed')->assertOk()->assertSee($post->title)->assertSee('উত্তর দরকার')->assertSee('রাশেদ');
        $this->get('/feed?category=health')->assertSee($post->title);
        $this->get('/feed?area=dhaka-division')->assertSee($post->title, false);
        $this->get('/feed?category=travel')->assertDontSee($post->title);
        $this->get($post->url())->assertOk()->assertSee($post->title)->assertSee('og:title', false);
        $this->assertDatabaseHas('analytics_events', ['name' => 'post_created']);
    }

    public function test_asking_validates_and_requires_a_name_once(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [, $token] = $this->member(null);

        $this->as($token)->postJson('/api/posts', ['title' => 'ছোট'])->assertJsonValidationErrors('title');
        $this->as($token)->postJson('/api/posts', ['title' => 'নাম ছাড়া একটা প্রশ্ন করা যায় কি?'])->assertJsonValidationErrors('name');
        $this->as($token)->postJson('/api/posts', ['title' => 'নাম দিয়ে একটা প্রশ্ন করা যায় কি?', 'name' => 'মিতু'])->assertCreated();
        $this->as($token)->postJson('/api/posts', ['title' => 'এবার নাম ছাড়াই আরেকটা প্রশ্ন, চলবে?'])->assertCreated();
        $this->as($token)->postJson('/api/posts', ['title' => 'অনেক লিংক http://a.com http://b.com http://c.com'])->assertJsonValidationErrors('body');
        $this->as($token)->postJson('/api/posts', ['title' => 'অজানা জেলার প্রশ্ন, কেমন হবে?', 'area' => 'atlantis'])->assertJsonValidationErrors('area');
    }

    public function test_a_double_submit_does_not_post_twice(): void
    {
        [, $token] = $this->member();
        $this->ask($token);
        $this->ask($token);

        $this->assertSame(1, Post::count());
    }

    public function test_answering_returns_rendered_html_and_counts(): void
    {
        [, $askerToken] = $this->member('আসিফ');
        $post = $this->ask($askerToken);
        [, $token] = $this->member('নাদিয়া');

        $data = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => "মিরপুর ১০-এ একটা ভালো ক্লিনিক আছে।\nদেখো: https://example.com/clinic."])
            ->assertCreated()->json();

        $this->assertSame(1, $data['answers_count']);
        $this->assertStringContainsString('নাদিয়া', $data['html']);
        $this->assertStringContainsString('<br>', $data['html']);
        $this->assertStringContainsString('href="https://example.com/clinic" rel="nofollow ugc noopener"', $data['html']);
        $this->assertSame(1, $post->fresh()->answers_count);

        $this->get($post->url())->assertSee('মিরপুর ১০-এ একটা ভালো ক্লিনিক আছে।');
        $this->get('/feed')->assertSee('১টি উত্তর');
        $this->get('/feed?tab=unanswered')->assertDontSee($post->title);
    }

    public function test_answer_body_is_escaped(): void
    {
        [, $askerToken] = $this->member();
        $post = $this->ask($askerToken);
        [, $token] = $this->member('মিতু');

        $html = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'দেখো "><img src=x onerror=alert(1)> এটা'])->json('html');

        $this->assertStringNotContainsString('<img src=x', $html); // (the edit form's photo picker has its own <img>)
    }

    public function test_helpful_toggles_once_per_member_and_not_on_your_own_post(): void
    {
        [, $askerToken] = $this->member();
        $post = $this->ask($askerToken);
        [, $token] = $this->member('মিতু');

        $this->as($token)->postJson('/api/helpful', ['type' => 'post', 'id' => $post->id])->assertJson(['marked' => true, 'count' => 1]);
        $this->as($token)->postJson('/api/helpful', ['type' => 'post', 'id' => $post->id])->assertJson(['marked' => false, 'count' => 0]);
        $this->as($token)->postJson('/api/helpful', ['type' => 'post', 'id' => $post->id])->assertJson(['marked' => true, 'count' => 1]);
        $this->as($askerToken)->postJson('/api/helpful', ['type' => 'post', 'id' => $post->id])->assertStatus(422);

        $this->assertSame(1, $post->fresh()->helpful_count);
    }

    public function test_only_the_author_accepts_an_answer_and_it_counts_on_the_helper_profile(): void
    {
        [, $askerToken] = $this->member();
        $post = $this->ask($askerToken);
        [$helper, $token] = $this->member('নাদিয়া');
        $answerId = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'মিরপুর ১০-এ যাও।'])->json('answer.id');

        $this->as($token)->postJson("/api/posts/{$post->id}/accept", ['answer' => $answerId])->assertForbidden();
        $this->as($askerToken)->postJson("/api/posts/{$post->id}/accept", ['answer' => $answerId])->assertJson(['accepted' => $answerId]);

        $this->get('/feed')->assertSee('সমাধান হয়েছে');
        $this->get("/u/{$helper->code}?tab=answers")->assertOk()->assertSee('নাদিয়া')->assertSee($post->title);
        $this->assertSame(1, Post::published()->whereIn('accepted_answer_id', Answer::select('id')->where('member_id', $helper->id))->count());

        $this->as($askerToken)->postJson("/api/posts/{$post->id}/accept", ['answer' => null])->assertJson(['accepted' => null]);
    }

    public function test_authors_delete_their_own_posts_and_answers_only(): void
    {
        [, $askerToken] = $this->member();
        $post = $this->ask($askerToken);
        [, $token] = $this->member('মিতু');
        $answerId = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'জানি না, তবে খুঁজে দেখবো।'])->json('answer.id');

        $this->as($askerToken)->deleteJson("/api/answers/{$answerId}")->assertForbidden();
        $this->as($token)->deleteJson("/api/answers/{$answerId}")->assertNoContent();
        $this->assertSame(0, $post->fresh()->answers_count);

        $this->as($token)->deleteJson("/api/posts/{$post->id}")->assertForbidden();
        $this->as($askerToken)->deleteJson("/api/posts/{$post->id}")->assertNoContent();
        $this->get($post->url())->assertNotFound();
        $this->get('/feed')->assertDontSee($post->title);
    }

    public function test_enough_reports_hide_a_post_until_an_admin_restores_it(): void
    {
        [, $askerToken] = $this->member();
        $post = $this->ask($askerToken);

        for ($i = 0; $i < Moderation::AUTO_HIDE_REPORTS; $i++) {
            [, $token] = $this->member(null);
            $this->as($token)->postJson('/api/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertNoContent();
            if ($i === 0) {
                $this->as($token)->postJson('/api/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'abuse'])->assertNoContent(); // a repeat is ignored
            }
        }

        $this->assertSame(Post::HIDDEN, $post->fresh()->status);
        $this->assertSame(Moderation::AUTO_HIDE_REPORTS, $post->fresh()->reports_count);
        $this->get($post->url())->assertNotFound();

        $this->actingAs(User::factory()->create());
        $this->get('/admin/community')->assertOk()->assertSee($post->title);
        $this->post("/admin/community/post/{$post->id}/dismiss")->assertRedirect();
        $this->assertSame([Post::PUBLISHED, 0], [$post->fresh()->status, $post->fresh()->reports_count]);
    }

    public function test_admin_can_remove_content_and_block_a_member(): void
    {
        [$member, $token] = $this->member();
        $post = $this->ask($token);

        $this->actingAs(User::factory()->create());
        $this->get('/admin/community?show=all')->assertOk()->assertSee($post->title);
        $this->post("/admin/community/post/{$post->id}/remove")->assertRedirect();
        $this->assertSame(Post::REMOVED, $post->fresh()->status);
        $this->post("/admin/community/members/{$member->id}/block")->assertRedirect();

        $this->as($token)->postJson('/api/posts', ['title' => 'ব্লক হওয়ার পরে প্রশ্ন করা যায়?'])->assertForbidden();
    }

    public function test_posting_is_rate_limited(): void
    {
        [, $token] = $this->member();
        foreach (range(1, 3) as $i) {
            $this->as($token)->postJson('/api/posts', ['title' => "দ্রুত পরপর প্রশ্ন নম্বর {$i}, চলবে?"])->assertCreated();
        }
        $this->as($token)->postJson('/api/posts', ['title' => 'চতুর্থ প্রশ্ন এক মিনিটের মধ্যে?'])->assertTooManyRequests();
    }

    public function test_feed_is_keyset_paginated_with_a_load_more_endpoint(): void
    {
        [$member] = $this->member();
        foreach (range(1, 20) as $i) {
            Post::create(['member_id' => $member->id, 'title' => "পরীক্ষার প্রশ্ন নম্বর {$i} এখানে"]);
        }

        $this->get('/feed')->assertSee('পরীক্ষার প্রশ্ন নম্বর 20 এখানে')->assertDontSee('পরীক্ষার প্রশ্ন নম্বর 5 এখানে')->assertSee('আরও দেখুন');

        $first = $this->getJson('/api/feed?limit=15')->assertOk()->json();
        $this->assertSame(15, $first['count']);
        $this->assertStringStartsWith('/feed?cursor=', $first['next']);

        $next = $this->getJson(str_replace('/feed', '/api/feed', $first['next']))->json();
        $this->assertSame(5, $next['count']);
        $this->assertStringContainsString('পরীক্ষার প্রশ্ন নম্বর 1 এখানে', $next['html']);
        $this->assertNull($next['next']);

        $this->assertSame(3, $this->getJson('/api/feed?limit=3&compact=1')->json('count'));

        // The page itself carries the next page for the endless list; later pages aren't indexed.
        $page = $this->get('/feed')->getContent();
        $this->assertSame(15, substr_count($page, 'data-item="post:'));
        $this->assertMatchesRegularExpression('~<a data-next href="(/feed\?cursor=[^"]+)"~', $page);
        $this->assertStringNotContainsString('noindex', $page);
        preg_match('~<a data-next href="([^"]+)"~', $page, $m);
        $second = $this->get(html_entity_decode($m[1]))->assertOk()->assertSee('noindex', false)->getContent();
        $this->assertSame(5, substr_count($second, 'data-item="post:'));
        $this->assertStringNotContainsString('data-next', $second);
    }

    public function test_the_solved_tab_and_profile_lists_page(): void
    {
        [$member] = $this->member();
        [$helper] = $this->member('Helper');
        foreach (range(1, 22) as $i) {
            $post = Post::create(['member_id' => $member->id, 'title' => "Profile paging question {$i} here"]);
            $answer = Answer::create(['post_id' => $post->id, 'member_id' => $helper->id, 'body' => "Answer {$i}"]);
            $post->update(['answers_count' => 1, 'accepted_answer_id' => $i <= 2 ? $answer->id : null]);
        }

        $this->assertSame(2, substr_count($this->get('/feed?tab=solved')->assertOk()->getContent(), 'data-item="post:'));

        $posts = $this->get("/u/{$member->code}")->assertOk()->getContent();
        $this->assertSame(20, substr_count($posts, 'data-item="post:'));
        $this->assertStringContainsString('data-next', $posts);
        $answers = $this->get("/u/{$helper->code}?tab=answers")->assertOk()->getContent();
        $this->assertSame(20, substr_count($answers, 'data-item="answer:'));
        preg_match('~<a data-next href="([^"]+)"~', $answers, $m);
        $this->assertSame(2, substr_count($this->get(html_entity_decode($m[1]))->getContent(), 'data-item="answer:'));
    }

    public function test_identity_can_be_restored_and_renamed(): void
    {
        [$member, $token] = $this->member();

        $this->as($token)->getJson('/api/members/me')->assertJson(['member' => ['code' => $member->code, 'name' => 'রাশেদ']]);
        $this->as($token)->patchJson('/api/members/me', ['name' => '<b>রাশেদ খান</b>'])->assertJson(['member' => ['name' => 'রাশেদ খান']]);
        $this->assertArrayNotHasKey('password', $member->fresh()->toArray());
    }

    public function test_anonymous_posts_and_answers_never_reveal_the_author_publicly(): void
    {
        [$asker, $askerToken] = $this->member('গোপন আসিফ');
        $post = $this->ask($askerToken, ['anonymous' => '1', 'category' => 'health']);
        [$helper, $helperToken] = $this->member('গোপন নাদিয়া');
        $answerHtml = $this->as($helperToken)->postJson("/api/posts/{$post->id}/answers", ['body' => 'বেনামে একটা উত্তর দিচ্ছি।', 'anonymous' => true])->json('html');
        Moderation::toggleHelpful($asker, Answer::first());

        $this->assertTrue($post->is_anonymous);
        $pages = [
            $this->get($post->url())->assertOk()->assertSee('বেনামী')->getContent(),
            $this->get('/feed')->assertOk()->getContent(),
            $this->getJson('/api/feed')->json('html'),
            $answerHtml,
        ];
        foreach ($pages as $html) {
            foreach ([$asker, $helper] as $m) {
                $this->assertStringNotContainsString($m->code, $html);
                $this->assertStringNotContainsString($m->name, $html);
            }
        }

        // Profiles leave anonymous items out, counts included.
        $this->get("/u/{$asker->code}")->assertOk()->assertDontSee($post->title);
        $this->get("/u/{$helper->code}")->assertOk()->assertDontSee('বেনামে একটা উত্তর দিচ্ছি।');

        // The author still gets owner controls through /api/mine.
        $this->as($askerToken)->postJson('/api/mine', ['items' => ["post:{$post->id}", 'answer:'.Answer::first()->id]])->assertExactJson(['mine' => ["post:{$post->id}"], 'saved' => []]);
        $this->as($helperToken)->postJson('/api/mine', ['items' => ["post:{$post->id}", 'answer:'.Answer::first()->id]])->assertExactJson(['mine' => ['answer:'.Answer::first()->id], 'saved' => []]);
        $this->flushHeaders()->postJson('/api/mine', ['items' => ["post:{$post->id}"]])->assertUnauthorized();

        // Admins see who wrote it.
        $this->actingAs(User::factory()->create());
        $this->get('/admin/community?show=all')->assertOk()->assertSee('গোপন আসিফ')->assertSee('anonymous');
    }
}
