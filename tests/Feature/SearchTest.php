<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Search;
use App\Community\Taxonomy;
use App\Models\Member;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MySQL's FULLTEXT index only sees committed rows, so these tests can't run inside RefreshDatabase's
 * transaction: they commit their posts and empty the community tables before and after each test.
 */
class SearchTest extends TestCase
{
    private const TABLES = ['helpful_marks', 'reports', 'member_notifications', 'answers', 'posts', 'member_tokens', 'member_identities', 'members', 'analytics_events'];

    protected function setUp(): void
    {
        parent::setUp();
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', ['--seeder' => QuizContentSeeder::class]);
            RefreshDatabaseState::$migrated = true;
        }
        $this->emptyCommunity();
        $this->beforeApplicationDestroyed(fn () => $this->emptyCommunity());
    }

    private function emptyCommunity(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLES as $table) {
            DB::table($table)->delete(); // not TRUNCATE: that rebuilds `posts` and its FULLTEXT index with stopwords back on
        }
        Schema::enableForeignKeyConstraints();
    }

    private function makePost(Member $member, string $title, ?string $body = null, array $extra = []): Post
    {
        return Post::create(['member_id' => $member->id, 'title' => $title, 'body' => $body] + $extra);
    }

    public function test_search_finds_bangla_and_english_words_inside_titles_and_bodies(): void
    {
        $m = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $renew = $this->makePost($m, 'পাসপোর্ট রিনিউ করতে কী কী কাগজ লাগে?');
        $lost = $this->makePost($m, 'হারানো পাসপোর্টের জন্য কী করব?', 'থানায় জিডি করেছি।');
        $this->makePost($m, 'Best dentist in Mirpur?', 'My tooth hurts after a root canal.');
        $this->makePost($m, 'পাসপোর্ট অফিসে ভিড়', null, ['status' => Post::HIDDEN]);

        // A word matches inside longer words (-এর endings), punctuation is ignored, hidden posts never show.
        $this->assertEqualsCanonicalizing([$renew->id, $lost->id], Search::posts('পাসপোর্ট!')->pluck('id')->all());
        $this->assertSame([$renew->id], Search::posts('পাসপোর্ট রিনিউ')->pluck('id')->all()); // every word must match
        $this->assertSame([$lost->id], Search::posts('জিডি')->pluck('id')->all()); // the body counts
        $this->assertSame(1, Search::posts('ROOT canal')->total());
        $this->assertNull(Search::posts('?! +-'));

        $this->get('/search?q=পাসপোর্ট')->assertOk()->assertSee('noindex', false)->assertSee($renew->title)->assertDontSee('অফিসে ভিড়');
        $this->get('/en/search?q=dentist')->assertOk()->assertSee('1 result');
        $this->get('/search?q=উটপাখি')->assertOk()->assertSee('href="/ask?title=', false);
        $this->get('/search')->assertOk()->assertDontSee('id="search-list"', false);
    }

    public function test_results_are_paged_with_an_endless_list(): void
    {
        $m = Accounts::signIn('email', 'a@example.test', 'Rashed');
        foreach (range(1, Search::PER_PAGE + 3) as $i) {
            $this->makePost($m, "Question number {$i} about visas");
        }

        $first = $this->get('/search?q=visas')->assertOk()->getContent();
        $this->assertSame(Search::PER_PAGE, substr_count($first, 'data-item="post:'));
        $this->assertMatchesRegularExpression('~data-next href="[^"]*page=2~', $first);
        $this->assertSame(3, substr_count($this->get('/search?q=visas&page=2')->getContent(), 'data-item="post:'));
    }

    public function test_similar_questions_share_meaningful_words(): void
    {
        $m = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $areas = Taxonomy::areas();
        $close = $this->makePost($m, 'How to renew a passport in Dhaka?', null, ['area_id' => $areas['dhaka']['id']]);
        $this->makePost($m, 'How to cook rice in a pressure cooker?');
        $this->makePost($m, 'Where to renew a driving licence?');

        $data = $this->getJson('/api/posts/similar?q=Can I renew my passport quickly')->assertOk()->json();
        $this->assertSame(1, $data['count']);
        $this->assertStringContainsString($close->url(), $data['html']);
        $this->assertStringContainsString('target="_blank"', $data['html']);

        // Common words ("how", "to", "in") alone don't make a question similar.
        $this->assertSame(0, $this->getJson('/api/posts/similar?q=How to do it in time')->json('count'));
    }
}
