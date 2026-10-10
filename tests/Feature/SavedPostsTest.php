<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Models\Member;
use App\Models\Post;
use App\Models\SavedPost;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavedPostsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    /** @return array{0: Member, 1: string} */
    private function member(string $name = 'রাশেদ'): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function save(string $token, int $post, bool $saved = true)
    {
        return $this->withHeader('X-Member-Token', $token)->postJson('/api/saved', ['post' => $post, 'saved' => $saved]);
    }

    public function test_saving_is_private_idempotent_and_listed_newest_first(): void
    {
        [$author] = $this->member('Author');
        [$reader, $token] = $this->member();
        [, $other] = $this->member('Other');
        $first = Post::create(['member_id' => $author->id, 'title' => 'First saved post']);
        $second = Post::create(['member_id' => $author->id, 'title' => 'Second saved post']);

        $this->save($token, $first->id)->assertOk()->assertJson(['saved' => true]);
        $this->save($token, $first->id)->assertOk(); // twice: still one row
        $this->save($token, $second->id)->assertOk();
        $this->assertSame(2, SavedPost::where('member_id', $reader->id)->count());

        $list = $this->withHeader('X-Member-Token', $token)->getJson('/api/saved')->assertOk();
        $this->assertSame([$second->id, $first->id], $list->json('ids'));
        $this->assertLessThan(strpos($list->json('html'), 'First saved post'), strpos($list->json('html'), 'Second saved post'));
        $this->assertStringContainsString('data-save="'.$second->id.'"', $list->json('html'));

        // Nobody else sees them, and the bookmark state is per member.
        $this->assertSame([], $this->withHeader('X-Member-Token', $other)->getJson('/api/saved')->json('ids'));
        $mine = $this->withHeader('X-Member-Token', $token)->postJson('/api/mine', ['items' => ["post:{$first->id}", "post:{$second->id}"]])->assertOk();
        $this->assertEqualsCanonicalizing(["post:{$first->id}", "post:{$second->id}"], $mine->json('saved'));
        $this->assertSame([], $this->withHeader('X-Member-Token', $other)->postJson('/api/mine', ['items' => ["post:{$first->id}"]])->json('saved'));

        // Unsaving (also twice) removes it.
        $this->save($token, $first->id, false)->assertOk()->assertJson(['saved' => false]);
        $this->save($token, $first->id, false)->assertOk();
        $this->assertSame([$second->id], $this->withHeader('X-Member-Token', $token)->getJson('/api/saved')->json('ids'));
    }

    public function test_needs_an_account_and_a_published_post(): void
    {
        [$author] = $this->member('Author');
        [, $token] = $this->member();
        $post = Post::create(['member_id' => $author->id, 'title' => 'Hidden later']);

        $this->postJson('/api/saved', ['post' => $post->id, 'saved' => true])->assertUnauthorized();
        $this->getJson('/api/saved')->assertUnauthorized();
        $this->save($token, 999999)->assertNotFound();
        $removed = Post::create(['member_id' => $author->id, 'title' => 'Gone', 'status' => Post::REMOVED]);
        $this->save($token, $removed->id)->assertNotFound();

        // Saved, then taken down: it drops out of the list, and comes back if restored.
        $this->save($token, $post->id)->assertOk();
        Moderation::setStatus($post, Post::HIDDEN);
        $this->assertSame([], $this->withHeader('X-Member-Token', $token)->getJson('/api/saved')->json('ids'));
        Moderation::setStatus($post->fresh(), Post::PUBLISHED);
        $this->assertSame([$post->id], $this->withHeader('X-Member-Token', $token)->getJson('/api/saved')->json('ids'));
    }

    public function test_the_list_pages(): void
    {
        [$author] = $this->member('Author');
        [$reader, $token] = $this->member();
        foreach (range(1, 22) as $i) {
            SavedPost::create(['member_id' => $reader->id, 'post_id' => Post::create(['member_id' => $author->id, 'title' => "Post {$i}"])->id]);
        }
        $page = $this->withHeader('X-Member-Token', $token)->getJson('/api/saved')->assertOk();
        $this->assertCount(20, $page->json('ids'));
        $rest = $this->withHeader('X-Member-Token', $token)->getJson('/api/saved?before='.$page->json('next'))->assertOk();
        $this->assertCount(2, $rest->json('ids'));
        $this->assertNull($rest->json('next'));
    }

    public function test_deleting_the_account_deletes_saved_posts(): void
    {
        [$author] = $this->member('Author');
        [$reader] = $this->member();
        SavedPost::create(['member_id' => $reader->id, 'post_id' => Post::create(['member_id' => $author->id, 'title' => 'Kept'])->id]);

        Accounts::delete($reader);
        $this->assertSame(0, SavedPost::where('member_id', $reader->id)->count());
    }

    public function test_pages_carry_the_bookmark_and_the_saved_page_is_a_cacheable_shell(): void
    {
        [$author] = $this->member('Author');
        $post = Post::create(['member_id' => $author->id, 'title' => 'A post to save']);

        foreach (['/', '/feed', $post->url()] as $url) {
            $this->get($url)->assertOk()->assertSee('data-save="'.$post->id.'"', false);
        }
        foreach (['/saved', '/en/saved'] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('saved-list', false);
            $this->assertEmpty($response->headers->getCookies());
            $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        }
        $this->get('/en/saved')->assertSee('Saved posts');
        $this->get('/privacy')->assertSee('সেভ করা পোস্ট');
        $this->get('/en/privacy')->assertSee('saved posts');
    }
}
