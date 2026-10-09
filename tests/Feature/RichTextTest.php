<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\RichText;
use App\Models\Answer;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function token(string $name = 'রাশেদ'): string
    {
        return Accounts::signIn('email', Str::random(10).'@example.test', $name)->issueToken();
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    private function ask(string $token, array $data = []): Post
    {
        $id = $this->as($token)->postJson('/api/posts', $data + ['title' => 'পাসপোর্ট করতে কী কী কাগজ লাগে?'])->assertCreated()->json('post.id');

        return Post::find($id);
    }

    public function test_cleaning_keeps_the_allowlist_and_drops_everything_else(): void
    {
        $html = RichText::clean('<p onclick="x()" style="color:red" class="big">এক <strong>দুই</strong> <em>তিন</em> <b>চার</b></p>'
            .'<script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://evil.test"></iframe>'
            .'<ul><li>প্রথম</li></ul><ol><li>দ্বিতীয়</li></ol><table><tr><td>ঘর</td></tr></table>');

        $this->assertSame('<p>এক <strong>দুই</strong> <em>তিন</em> চার</p><ul><li>প্রথম</li></ul><ol><li>দ্বিতীয়</li></ol>ঘর', $html);
    }

    public function test_links_are_safe_and_marked_as_user_content(): void
    {
        $html = RichText::clean('<p><a href="javascript:alert(1)">ক</a> <a href="data:text/html,x">খ</a> <a href="https://example.com/a" target="_self" rel="dofollow">গ</a> <a href="mailto:a@example.test">ঘ</a> দেখুন https://example.com/b.</p>');

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:', $html);
        $this->assertStringContainsString('<p>ক খ <a href="https://example.com/a"', $html);
        $this->assertSame(2, substr_count($html, 'rel="nofollow noreferrer noopener" target="_blank"')); // web links; mailto opens the mail app
        $this->assertStringContainsString('<a href="mailto:a@example.test">ঘ</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com/b" rel="nofollow noreferrer noopener" target="_blank">https://example.com/b</a>.', $html);
        $this->assertSame(3, RichText::linkCount($html));
        $this->assertSame(1, RichText::linkCount('<p>শুধু লেখা www.example.com</p>'));
    }

    public function test_headings_are_for_posts_only_and_empty_formatting_is_nothing(): void
    {
        $this->assertSame('<h3>কাগজ</h3><p>লেখা</p>', RichText::clean('<h3>কাগজ</h3><p>লেখা</p>', headings: true));
        $this->assertSame('কাগজ<p>লেখা</p>', RichText::clean('<h3>কাগজ</h3><p>লেখা</p>'));
        $this->assertSame('বড় শিরোনাম', RichText::clean('<h1>বড় শিরোনাম</h1>', headings: true));
        $this->assertSame('<p>লেখা</p>', RichText::clean('<p>লেখা</p><p></p><p><br></p>'));
        $this->assertNull(RichText::clean('<p></p><p><strong> </strong></p><ul><li></li></ul>'));
    }

    public function test_the_plain_text_copy_keeps_lines_and_list_items(): void
    {
        $text = RichText::toText('<h3>কাগজপত্র</h3><p>যা লাগবে:<br />সব মূল কপি</p><ul><li>জন্ম নিবন্ধন</li><li>এনআইডি &amp; ছবি</li></ul><p>শেষ কথা</p>');

        $this->assertSame("কাগজপত্র\n\nযা লাগবে:\nসব মূল কপি\n\n• জন্ম নিবন্ধন\n• এনআইডি & ছবি\n\nশেষ কথা", $text);
    }

    public function test_a_formatted_post_stores_both_versions_and_renders_the_html(): void
    {
        $post = $this->ask($this->token(), ['format' => 'html', 'body' => '<h3>কী লাগে</h3><p>আমার কাছে <strong>এনআইডি</strong> আছে।</p><script>alert(1)</script><ul><li>জন্ম নিবন্ধন</li></ul>']);

        $this->assertSame('<h3>কী লাগে</h3><p>আমার কাছে <strong>এনআইডি</strong> আছে।</p><ul><li>জন্ম নিবন্ধন</li></ul>', $post->body_html);
        $this->assertSame("কী লাগে\n\nআমার কাছে এনআইডি আছে।\n\n• জন্ম নিবন্ধন", $post->body);
        $this->get($post->url())->assertOk()
            ->assertSee('<strong>এনআইডি</strong>', false)->assertSee('<ul><li>জন্ম নিবন্ধন</li></ul>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/feed')->assertSee('কী লাগে আমার কাছে এনআইডি আছে।') // the card excerpt is the plain text
            ->assertDontSee('&lt;strong&gt;', false);
    }

    public function test_without_the_editor_it_is_plain_text_as_before(): void
    {
        $post = $this->ask($this->token(), ['body' => "<b>এটা</b> সাধারণ লেখা\nদুই লাইন"]);

        $this->assertNull($post->body_html);
        $this->assertSame("এটা সাধারণ লেখা\nদুই লাইন", $post->body);
        $this->get($post->url())->assertSee('এটা সাধারণ লেখা<br>', false);
    }

    public function test_formatted_answers_have_no_headings_and_replies_stay_plain(): void
    {
        $post = $this->ask($this->token());
        $token = $this->token('মিতু');

        $html = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['format' => 'html', 'body' => '<h3>ধাপ</h3><ol><li>অনলাইনে আবেদন</li><li>ব্যাংকে ফি</li></ol>'])
            ->assertCreated()->json('html');
        $answer = Answer::latest('id')->first();
        $this->assertSame('ধাপ<ol><li>অনলাইনে আবেদন</li><li>ব্যাংকে ফি</li></ol>', $answer->body_html);
        $this->assertStringContainsString('<ol><li>অনলাইনে আবেদন</li>', $html);

        $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['parent' => $answer->id, 'format' => 'html', 'body' => '<p><strong>আরেকটা</strong> কথা</p>'])->assertCreated();
        $reply = Answer::latest('id')->first();
        $this->assertNull($reply->body_html);
        $this->assertSame('আরেকটা কথা', $reply->body);
    }

    public function test_link_and_length_limits_count_the_formatted_version(): void
    {
        $token = $this->token();
        $three = '<p><a href="https://a.example">ক</a> <a href="https://b.example">খ</a> <a href="https://c.example">গ</a></p>';

        $this->as($token)->postJson('/api/posts', ['title' => 'তিনটা লিংক দেওয়া যাবে কি এখানে?', 'format' => 'html', 'body' => $three])->assertJsonValidationErrors('body');
        $post = $this->ask($token);
        $this->as($this->token('মিতু'))->postJson("/api/posts/{$post->id}/answers", ['format' => 'html', 'body' => $three])->assertJsonValidationErrors('body');
        $this->as($token)->postJson('/api/posts', ['title' => 'খুব লম্বা লেখা দেওয়া যাবে কি?', 'format' => 'html', 'body' => str_repeat('<p><strong>ক</strong></p>', 1200)])->assertJsonValidationErrors('body');
        $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['format' => 'html', 'body' => '<p><br></p>'])->assertJsonValidationErrors('body');
    }

    public function test_editing_switches_between_formatted_and_plain(): void
    {
        $token = $this->token();
        $post = $this->ask($token, ['format' => 'html', 'body' => '<p><em>প্রথম</em> লেখা</p>']);

        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => $post->title, 'format' => 'html', 'body' => '<p><strong>নতুন</strong> লেখা</p>'])
            ->assertOk()->assertJson(['body' => 'নতুন লেখা', 'body_html' => '<p><strong>নতুন</strong> লেখা</p>']);
        $this->assertNotNull($post->fresh()->edited_at);

        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => $post->title, 'body' => 'শুধু লেখা'])
            ->assertOk()->assertJson(['body' => 'শুধু লেখা', 'body_html' => 'শুধু লেখা']);
        $this->assertNull($post->fresh()->body_html);
    }
}
