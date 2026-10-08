<?php

namespace Tests\Feature;

use App\Analytics\Funnel;
use App\Models\AnalyticsEvent;
use App\Models\QuizResult;
use App\Quiz\QuizConfig;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function answers(int $pick = 0): array
    {
        return array_map(fn ($o) => array_keys($o)[$pick], array_values(QuizConfig::fromDatabase()->questions));
    }

    private function play(array $extra = [], int $pick = 0): array
    {
        return $this->postJson('/api/results', ['answers' => $this->answers($pick), 'visitor_id' => (string) Str::uuid()] + $extra)
            ->assertCreated()->json();
    }

    public function test_landing_page_is_cookieless_and_cacheable(): void
    {
        $response = $this->get('/')->assertOk()->assertSee('তোমার বাংলাদেশ', false);

        $this->assertEmpty($response->headers->getCookies());
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $response->assertSee('og:image', false);
    }

    public function test_cached_quiz_content_does_not_bake_in_the_host(): void
    {
        $this->get('/')->assertOk();
        config(['app.url' => 'https://other.example']);
        url()->forceRootUrl('https://other.example');

        $this->get('/')->assertOk()
            ->assertSee('"illustration":"/images/locations/', false)
            ->assertDontSee('localhost', false);
    }

    public function test_completing_the_quiz_stores_a_result_and_returns_it(): void
    {
        $data = $this->play();

        $this->assertSame(40, strlen($data['owner_token']));
        $this->assertNotEmpty($data['result']['location']['name_bn']);
        $this->assertCount(5, $data['result']['traits']);
        $this->assertStringEndsWith('/r/'.$data['result']['code'], $data['result']['url']);
        $this->assertDatabaseHas('analytics_events', ['name' => 'quiz_completed']);
        $this->assertArrayNotHasKey('owner_token_hash', QuizResult::first()->toArray());
    }

    public function test_result_ranks_traits_and_explains_every_place(): void
    {
        $result = $this->play(pick: 1)['result'];
        $answers = $this->answers(1);

        $pcts = array_column($result['traits'], 'pct');
        $sorted = $pcts;
        rsort($sorted);
        $this->assertSame($sorted, $pcts, 'traits are listed strongest first');

        $places = $result['places'];
        $this->assertCount(count(QuizConfig::fromDatabase()->locations), $places);
        $this->assertSame([$result['location']['slug'], $result['match_pct']], [$places[0]['slug'], $places[0]['pct']]);
        foreach (array_slice($places, 1) as $place) {
            $this->assertLessThanOrEqual($result['second']['pct'], $place['pct']);
            $this->assertNotEmpty($place['answers']);
            $this->assertEmpty(array_diff($place['answers'], $answers), 'only the player\'s own answers are quoted');
        }
    }

    public function test_invalid_answers_are_rejected(): void
    {
        $this->postJson('/api/results', ['answers' => [1, 2]])->assertStatus(422);
        $this->postJson('/api/results', ['answers' => 'x'])->assertStatus(422);
    }

    public function test_share_page_has_personal_preview_tags_and_is_noindexed(): void
    {
        $data = $this->play();
        $result = QuizResult::where('code', $data['result']['code'])->first();
        $result->update(['display_name' => 'রাশেদ']);

        $this->get('/r/'.$result->code)->assertOk()
            ->assertSee('<meta property="og:title" content="রাশেদের বাংলাদেশ হলো '.$result->location->name_bn, false)
            ->assertSee('images/og/'.$result->location->slug.'.png', false)
            ->assertSee('noindex', false);

        $this->get('/r/doesnotexist')->assertNotFound();
    }

    public function test_only_the_owner_can_name_a_result_and_names_are_cleaned(): void
    {
        $data = $this->play();
        $code = $data['result']['code'];

        $this->patchJson("/api/results/{$code}/name", ['owner_token' => str_repeat('x', 40), 'name' => 'Hacker'])->assertForbidden();

        $this->patchJson("/api/results/{$code}/name", ['owner_token' => $data['owner_token'], 'name' => '<b>রাশেদ</b> www.x.com'])
            ->assertOk()->assertJsonPath('result.name', 'রাশেদ');
    }

    public function test_a_friend_from_a_shared_link_gets_a_comparison(): void
    {
        $first = $this->play();
        $friend = $this->play(['ref' => $first['result']['code']], pick: 1);

        $this->assertNotNull($friend['result']['friend']);
        $this->assertIsInt($friend['result']['friend']['pct']);
        $this->assertSame(1, QuizResult::whereNotNull('referrer_result_id')->count());
    }

    public function test_events_accept_beacon_batches_and_ignore_unknown_names(): void
    {
        $data = $this->play();
        $body = json_encode([
            'visitor_id' => (string) Str::uuid(),
            'events' => [
                ['name' => 'share_clicked', 'result' => $data['result']['code'], 'meta' => ['channel' => 'whatsapp', 'nested' => ['x']]],
                ['name' => 'question_answered', 'meta' => ['q' => 3]],
            ],
        ]);

        $this->call('POST', '/api/events', [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body)->assertNoContent();

        $event = AnalyticsEvent::where('name', 'share_clicked')->first();
        $this->assertSame(['channel' => 'whatsapp'], $event->meta);
        $this->assertNotNull($event->quiz_result_id);

        $this->postJson('/api/events', ['events' => [['name' => 'drop_table']]])->assertStatus(422);
    }

    public function test_funnel_counts_people_so_replays_never_push_completion_past_100_percent(): void
    {
        $visitor = (string) Str::uuid();
        $this->postJson('/api/events', ['visitor_id' => $visitor, 'events' => [['name' => 'landing_view'], ['name' => 'quiz_started']]])->assertNoContent();
        $answers = $this->answers();
        foreach ([0, 1, 2] as $_) {
            $this->postJson('/api/results', ['answers' => $answers, 'visitor_id' => $visitor])->assertCreated();
        }
        // Someone who finished without a "started" event in this period is not counted as a completer.
        $this->postJson('/api/results', ['answers' => $answers, 'visitor_id' => (string) Str::uuid()])->assertCreated();

        $summary = (new Funnel(now()->subDay()))->summary();
        $this->assertSame(1, $summary['started']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(4, $summary['plays']);
        $this->assertEquals(100, $summary['completion_rate']);

        $trend = (new Funnel(now()->subDays(6)->startOfDay()))->trend();
        $this->assertCount(7, $trend);
        $this->assertSame(['visitors' => 1, 'completed' => 2], array_intersect_key(end($trend), ['visitors' => 0, 'completed' => 0]));
    }

    public function test_referral_numbers_count_people_and_split_start_rate_by_entry(): void
    {
        $owner = (string) Str::uuid();
        $this->postJson('/api/events', ['visitor_id' => $owner, 'events' => [['name' => 'landing_view'], ['name' => 'quiz_started', 'meta' => ['referred' => 0]]]])->assertNoContent();
        $code = $this->postJson('/api/results', ['answers' => $this->answers(), 'visitor_id' => $owner])->json('result.code');

        // A friend opens the link and plays three times; a second link visitor never presses play.
        $friend = (string) Str::uuid();
        $this->postJson('/api/events', ['visitor_id' => $friend, 'events' => [['name' => 'share_page_view', 'result' => $code], ['name' => 'quiz_started', 'meta' => ['referred' => 1]]]])->assertNoContent();
        foreach ([0, 1, 2] as $_) {
            $this->postJson('/api/results', ['answers' => $this->answers(), 'visitor_id' => $friend, 'ref' => $code])->assertCreated();
        }
        $this->postJson('/api/events', ['visitor_id' => (string) Str::uuid(), 'events' => [['name' => 'share_page_view', 'result' => $code]]])->assertNoContent();

        $funnel = new Funnel(now()->subDay());
        $summary = $funnel->summary();
        $this->assertSame(1, $summary['referred_players']);
        $this->assertEquals(50, $summary['referral_conversion']);
        $this->assertEquals(1, $summary['viral_k']);

        $entries = $funnel->startRateByEntry();
        $this->assertSame(['visitors' => 1, 'started' => 1, 'rate' => 100.0], $entries['direct']);
        $this->assertSame(['visitors' => 2, 'started' => 1, 'rate' => 50.0], $entries['link']);
    }
}
