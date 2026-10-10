<?php

namespace Tests\Feature;

use App\Analytics\CommunityStats;
use App\Community\Accounts;
use App\Community\Taxonomy;
use App\Community\Views;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostViewsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function read(Post $post, ?string $visitor, array $headers = [])
    {
        return $this->flushHeaders()->withHeaders(array_filter($headers + ['X-Visitor' => $visitor, 'User-Agent' => 'Mozilla/5.0 (Linux; Android 13) Mobile']))
            ->post("/api/posts/{$post->id}/view")->assertNoContent();
    }

    public function test_a_view_counts_once_a_day_per_browser_and_never_the_author_or_bots(): void
    {
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        $post = Post::create(['member_id' => $author->id, 'title' => 'A post people read']);
        $before = $post->fresh()->updated_at;
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];

        $this->read($post, $a);
        $this->read($post, $a); // the same browser again today
        $this->read($post, $b);
        $this->assertSame(2, $post->fresh()->views_count);
        $this->assertEquals($before, $post->fresh()->updated_at); // reading doesn't "update" the post

        $this->read($post, (string) Str::uuid(), ['X-Member-Token' => $author->issueToken()]); // the author
        $this->read($post, (string) Str::uuid(), ['User-Agent' => 'facebookexternalhit/1.1']); // a link preview bot
        $this->read($post, null); // no visitor id
        $this->read($post, 'not-a-uuid');
        $this->assertSame(2, $post->fresh()->views_count);

        // A day later the same browser counts again.
        $this->travel(Views::DAY_SECONDS + 1)->seconds();
        $this->read($post, $a);
        $this->assertSame(3, $post->fresh()->views_count);

        // Unpublished posts don't count; unknown ones are a 404.
        $post->update(['status' => Post::HIDDEN]);
        $this->read($post->fresh(), (string) Str::uuid());
        $this->assertSame(3, $post->fresh()->views_count);
        $this->postJson('/api/posts/999999/view')->assertNotFound();

        // Cookieless, like every public endpoint.
        $this->assertEmpty($this->withHeader('X-Visitor', (string) Str::uuid())->post("/api/posts/{$post->id}/view")->headers->getCookies());
    }

    public function test_the_count_shows_on_the_post_from_ten_views(): void
    {
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        $post = Post::create(['member_id' => $author->id, 'title' => 'Counted post']);

        $post->forceFill(['views_count' => Views::SHOW_FROM - 1])->save();
        $this->get($post->url())->assertOk()->assertSee('data-post-view="'.$post->id.'"', false)->assertDontSee('বার দেখা হয়েছে');

        $post->forceFill(['views_count' => 1234])->save();
        $this->get($post->url())->assertSee('১২৩৪ বার দেখা হয়েছে');
        $this->get('/en'.$post->url())->assertSee('1234 views');
    }

    public function test_the_dashboard_shows_views_by_topic_and_the_most_read(): void
    {
        $author = Accounts::signIn('email', 'author@example.test', 'Author');
        $health = Taxonomy::categories()['health']['id'];
        Post::create(['member_id' => $author->id, 'title' => 'Doctor question', 'category_id' => $health])->forceFill(['views_count' => 30])->save();
        Post::create(['member_id' => $author->id, 'title' => 'Another health one', 'category_id' => $health])->forceFill(['views_count' => 5])->save();
        Post::create(['member_id' => $author->id, 'title' => 'No topic post'])->forceFill(['views_count' => 12])->save();
        Post::create(['member_id' => $author->id, 'title' => 'Hidden', 'status' => Post::HIDDEN])->forceFill(['views_count' => 99])->save();

        $views = (new CommunityStats(now()->subDays(7)))->views();
        $this->assertSame(47, $views['total']);
        $this->assertSame([['name' => 'Health', 'views' => 35, 'posts' => 2], ['name' => 'No topic', 'views' => 12, 'posts' => 1]], $views['by_category']);
        $this->assertSame(['Doctor question', 'No topic post', 'Another health one'], array_column($views['top'], 'title'));

        $this->actingAs(User::factory()->create())->get('/admin?days=7')->assertOk()->assertSee('Views by topic')->assertSee('Doctor question');
    }
}
