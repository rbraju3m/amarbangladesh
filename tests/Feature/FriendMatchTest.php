<?php

namespace Tests\Feature;

use App\Analytics\Funnel;
use App\Models\QuizResult;
use App\Quiz\QuizConfig;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FriendMatchTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function answers(int $pick): array
    {
        return array_map(fn ($o) => array_keys($o)[$pick], array_values(QuizConfig::fromDatabase()->questions));
    }

    private function play(int $pick = 0, ?string $ref = null, ?string $visitor = null, ?string $name = null): array
    {
        $data = $this->postJson('/api/results', [
            'answers' => $this->answers($pick),
            'visitor_id' => $visitor ?? (string) Str::uuid(),
            'ref' => $ref,
        ])->assertCreated()->json();

        if ($name !== null) {
            $this->patchJson("/api/results/{$data['result']['code']}/name", ['owner_token' => $data['owner_token'], 'name' => $name])->assertOk();
        }

        return $data;
    }

    private function friends(array $owner): array
    {
        return $this->postJson("/api/results/{$owner['result']['code']}/friends", ['owner_token' => $owner['owner_token']])
            ->assertOk()->json();
    }

    public function test_only_the_owner_can_see_the_list(): void
    {
        $owner = $this->play();
        $code = $owner['result']['code'];

        $this->postJson("/api/results/{$code}/friends")->assertUnprocessable();
        $this->postJson("/api/results/{$code}/friends", ['owner_token' => str_repeat('x', 40)])->assertForbidden();
        $this->postJson('/api/results/nope1234/friends', ['owner_token' => $owner['owner_token']])->assertNotFound();
    }

    public function test_a_result_without_friends_has_an_empty_list(): void
    {
        $this->assertSame(['total' => 0, 'friends' => []], $this->friends($this->play()));
    }

    public function test_friends_are_listed_best_match_first(): void
    {
        $owner = $this->play(0);
        $code = $owner['result']['code'];
        $this->play(0, $code, name: 'রিনা');
        $this->play(1, $code);
        $this->play(2, $code, name: 'Sam');
        $this->play(3, $code);

        $data = $this->friends($owner);

        $this->assertSame(4, $data['total']);
        $pcts = array_column($data['friends'], 'pct');
        $sorted = $pcts;
        rsort($sorted);
        $this->assertSame($sorted, $pcts);

        $expected = QuizResult::where('referrer_result_id', QuizResult::where('code', $code)->value('id'))->pluck('friend_match_pct')->sort()->reverse()->values()->all();
        $this->assertSame($expected, $pcts);

        $rina = collect($data['friends'])->firstWhere('name', 'রিনা');
        $this->assertSame(true, $rina['same'], 'same answers, same place');
        $this->assertSame($owner['result']['location']['slug'], $rina['location']['slug']);
        $this->assertNotEmpty($rina['ago']);
        $this->assertContains(null, array_column($data['friends'], 'name'), 'unnamed friends stay unnamed');
    }

    public function test_a_friend_replaying_counts_once_and_the_owner_not_at_all(): void
    {
        $visitor = (string) Str::uuid();
        $owner = $this->play(0, visitor: $visitor);
        $code = $owner['result']['code'];

        $friend = (string) Str::uuid();
        $this->play(0, $code, $friend);
        $latest = $this->play(2, $code, $friend);
        $this->play(1, $code, $visitor); // the owner trying their own link

        $data = $this->friends($owner);

        $this->assertSame(1, $data['total']);
        $this->assertSame($latest['result']['friend']['pct'], $data['friends'][0]['pct']);
        $this->assertSame($latest['result']['location']['slug'], $data['friends'][0]['location']['slug']);
    }

    public function test_the_list_holds_nothing_private_about_friends(): void
    {
        $owner = $this->play();
        $friend = $this->play(1, $owner['result']['code'], name: 'রিনা');

        $data = $this->friends($owner);
        $json = json_encode($data);

        $this->assertSame(['name', 'location', 'pct', 'same', 'ago'], array_keys($data['friends'][0]));
        $this->assertStringNotContainsString($friend['result']['code'], $json);
        $this->assertStringNotContainsString(QuizResult::where('code', $friend['result']['code'])->value('visitor_id'), $json);
    }

    public function test_friends_with_the_same_answers_match_almost_fully(): void
    {
        $owner = $this->play(0);
        $friend = $this->play(0, $owner['result']['code']);

        // The owner's vector comes back from MySQL JSON with its keys reordered; the match must not care.
        $this->assertGreaterThanOrEqual(97, $friend['result']['friend']['pct']);
        $this->assertGreaterThanOrEqual(97, $this->friends($owner)['friends'][0]['pct']);
    }

    public function test_other_links_friends_are_not_mixed_in(): void
    {
        $owner = $this->play();
        $other = $this->play();
        $this->play(1, $other['result']['code']);

        $this->assertSame(0, $this->friends($owner)['total']);
        $this->assertSame(1, $this->friends($other)['total']);
    }

    public function test_dashboard_counts_owners_who_came_back_for_their_friends(): void
    {
        $owner = $this->play();
        $code = $owner['result']['code'];
        $send = fn (string $visitor, array $meta) => $this->postJson('/api/events', [
            'visitor_id' => $visitor,
            'events' => [['name' => 'friends_viewed', 'result' => $code, 'meta' => $meta]],
        ])->assertNoContent();

        $send($a = (string) Str::uuid(), ['count' => 3, 'new' => 2]);
        $send($a, ['count' => 3, 'new' => 0]); // the same owner again
        $send((string) Str::uuid(), ['count' => 1, 'new' => 0]);
        $send((string) Str::uuid(), ['count' => 0, 'new' => 0]); // an empty list isn't "came back for friends"
        $this->postJson('/api/events', ['events' => [['name' => 'share_clicked', 'result' => $code, 'meta' => ['channel' => 'friends_empty']]]])->assertNoContent();

        $funnel = new Funnel(now()->subDay());
        $summary = $funnel->summary();
        $this->assertSame(2, $summary['friends_viewers']);
        $this->assertSame(1, $summary['friends_viewers_new']);
        $this->assertSame(1, $funnel->shareChannels()['friends_empty']);
    }

    public function test_english_pages_get_english_place_names(): void
    {
        $owner = $this->play();
        $this->play(0, $owner['result']['code']);

        $data = $this->withHeader('X-Locale', 'en')
            ->postJson("/api/results/{$owner['result']['code']}/friends", ['owner_token' => $owner['owner_token']])
            ->assertOk()->json();

        $this->assertMatchesRegularExpression('/^[A-Za-z\' ]+$/', $data['friends'][0]['location']['name']);
        $this->assertSame('just now', $data['friends'][0]['ago']);
    }
}
