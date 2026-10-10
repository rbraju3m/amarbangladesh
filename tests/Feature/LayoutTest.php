<?php

namespace Tests\Feature;

use App\Community\DemoContent;
use App\Models\Member;
use App\Models\Post;
use App\Quiz\QuizConfig;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); // demo content includes photos
    }

    protected $seeder = QuizContentSeeder::class;

    /** Quiz and community pages share one header, footer and phone tab bar, in both languages. */
    public function test_every_public_page_uses_the_shared_shell(): void
    {
        DemoContent::seed();
        $result = $this->postJson('/api/results', ['answers' => $this->answers(), 'visitor_id' => (string) Str::uuid()])->json('result.code');
        $post = Post::value('id');
        $member = Member::where('is_demo', true)->whereHas('posts', fn ($q) => $q->where('is_anonymous', false))->value('code');

        foreach (['', '/en'] as $prefix) {
            foreach (['/', '/quiz', "/r/{$result}", '/feed', "/p/{$post}", '/ask', "/u/{$member}", '/me', '/notifications', '/privacy'] as $path) {
                $url = $prefix.($path === '/' && $prefix ? '' : $path);
                $html = $this->get($url)->assertOk()->getContent();

                $this->assertSame(1, substr_count($html, '<header class="sticky top-0'), "header on {$url}");
                $this->assertStringContainsString('aria-label="'.($prefix ? 'Footer' : 'ফুটার').'"', $html, "footer on {$url}");
                $this->assertStringContainsString('href="'.$prefix.'/feed"', $html, "community link on {$url}");
                $this->assertStringContainsString('href="'.$prefix.'/quiz"', $html, "quiz link on {$url}");
                $this->assertSame(2, substr_count($html, "localStorage.getItem('bd.member')"), "signed-in state set before paint (header + tab bar) on {$url}");
            }
        }
    }

    private function answers(): array
    {
        return array_map(fn ($o) => array_keys($o)[0], array_values(QuizConfig::fromDatabase()->questions));
    }
}
