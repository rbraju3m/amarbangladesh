<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_posts_preview_as_articles_with_the_community_card(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $post = Post::create(['member_id' => $member->id, 'title' => 'Where to renew a passport?', 'body' => 'Mine expired last month.']);

        $html = $this->get($post->url())->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Where to renew a passport?">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Mine expired last month.">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('images/og/community.png').'">', $html); // not the quiz's
        $this->assertStringContainsString('content="'.asset('images/og/en/community.png').'"', $this->get('/en'.$post->url())->getContent());
        $this->assertStringContainsString('<meta property="og:type" content="website">', $this->get('/feed')->getContent());
        $this->assertStringContainsString(asset('images/og/community.png'), $this->get('/')->getContent());
        $this->assertStringContainsString(asset('images/og/default.png'), $this->get('/quiz')->getContent()); // the quiz keeps its own
        $this->assertFileExists(public_path('images/og/community.png'));
        $this->assertFileExists(public_path('images/og/en/community.png'));
    }

    public function test_share_buttons_on_cards_the_post_and_the_sheet(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $post = Post::create(['member_id' => $member->id, 'title' => 'Best "tea" in Sylhet?']);

        // Cards: plain HTML with the full link and the title (escaped), handled by the community script.
        $this->get('/feed')->assertOk()
            ->assertSee('data-share="'.url($post->url()).'" data-title="Best &quot;tea&quot; in Sylhet?"', false);

        $html = $this->get($post->url())->getContent();
        $this->assertStringContainsString('aria-labelledby="share-title"', $html); // the sheet
        $this->assertStringContainsString("shareTo('facebook'", $html); // unanswered: Facebook among the quick buttons
        $this->assertStringContainsString("'post')\">", $html); // the post's share button says where it came from
    }
}
