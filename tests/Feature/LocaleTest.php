<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\Post;
use App\Models\QuizResult;
use App\Quiz\QuizConfig;
use App\Quiz\ResultPresenter;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_every_public_page_exists_in_both_languages(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'রাশেদ');
        $post = Post::create(['member_id' => $member->id, 'title' => 'দুই ভাষার একটা পরীক্ষার প্রশ্ন']);

        foreach (['', '/feed', '/ask', '/me', '/notifications', "/p/{$post->id}", "/u/{$member->code}", '/auth/done', '/reset-password'] as $path) {
            $this->get($path ?: '/')->assertOk()->assertSee('<html lang="bn">', false);
            $response = $this->get('/en'.$path)->assertOk()->assertSee('<html lang="en">', false);
            $this->assertEmpty($response->headers->getCookies(), "/en{$path} stays cookieless");
        }
    }

    public function test_english_pages_link_within_english_and_declare_alternates(): void
    {
        $html = $this->get('/en/feed?category=health')->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="bn" href="'.url('/feed').'?category=health"', $html);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/feed').'?category=health"', $html);
        $this->assertStringContainsString('href="/en/ask', $html);
        $this->assertStringContainsString('lang="bn" class="lang-switch', $html); // switch back to Bangla
        $this->assertStringNotContainsString('href="/ask', $html);

        $this->get('/feed')->assertSee('href="'.url('/en/feed').'"', false)->assertSee('>English<', false);
    }

    public function test_api_answers_in_the_pages_language(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'রাশেদ');
        Post::create(['member_id' => $member->id, 'title' => 'দুই ভাষার একটা পরীক্ষার প্রশ্ন']);
        $token = $member->issueToken();

        $this->assertStringContainsString('href="/en/p/', $this->getJson('/api/feed?lang=en')->json('html'));
        $this->assertStringContainsString('href="/p/', $this->getJson('/api/feed')->json('html'));
        $this->withHeaders(['X-Member-Token' => $token, 'X-Locale' => 'en'])
            ->postJson('/api/posts', ['title' => 'English page question title'])->assertJsonPath('post.url', fn ($url) => str_starts_with($url, '/en/p/'));
    }

    public function test_the_quiz_plays_in_english_with_english_content(): void
    {
        $this->get('/en/quiz')->assertOk()
            ->assertSee('Where is your', false)
            ->assertSee('"prompt":"A 3-day holiday! Where are you packing your bag for?"', false)
            ->assertSee('"title":"The calm mind of the tea gardens"', false);

        $answers = array_map(fn ($o) => array_keys($o)[1], array_values(QuizConfig::fromDatabase()->questions));
        $result = $this->withHeader('X-Locale', 'en')->postJson('/api/results', ['answers' => $answers])->assertCreated()->json('result');

        $this->assertStringContainsString('/en/r/', $result['url']);
        $this->assertDoesNotMatchRegularExpression('/[\x{0980}-\x{09FF}]/u', $result['reason'].$result['location']['title'].$result['location']['name']);
        $this->assertMatchesRegularExpression('/^[A-Z].+ and .+ — .+/u', $result['reason']);

        // The same result in Bangla keeps the Bangla sentence; both are stored.
        $stored = QuizResult::where('code', $result['code'])->first();
        $this->assertNotEmpty($stored->reason_bn);
        $this->assertSame($result['reason'], $stored->reason_en);
        $this->flushHeaders()->get('/r/'.$result['code'])->assertOk()->assertSee($stored->reason_bn, false);
        $this->get('/en/r/'.$result['code'])->assertOk()->assertSee('Bangladesh is', false);
        $this->get('/en/r/'.$result['code'])->assertSee('/images/og/en/', false);
        $this->get('/r/'.$result['code'])->assertDontSee('/images/og/en/', false);
    }

    public function test_older_results_get_an_english_reason_rebuilt_from_their_answers(): void
    {
        $answers = array_map(fn ($o) => array_keys($o)[0], array_values(QuizConfig::fromDatabase()->questions));
        $code = $this->postJson('/api/results', ['answers' => $answers])->json('result.code');
        QuizResult::where('code', $code)->update(['reason_en' => null]); // as before the English content existed

        app()->setLocale('en');
        $presented = ResultPresenter::present(QuizResult::where('code', $code)->first());
        $this->assertMatchesRegularExpression('/^[A-Z][^\x{0980}-\x{09FF}]+ — [^\x{0980}-\x{09FF}]+$/u', $presented['reason']);
    }
}
