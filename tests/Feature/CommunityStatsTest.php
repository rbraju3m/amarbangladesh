<?php

namespace Tests\Feature;

use App\Analytics\CommunityStats;
use App\Community\Accounts;
use App\Community\Moderation;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunityStatsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private const NOW = '2026-10-14 12:00:00'; // a Wednesday, Dhaka time

    private Member $asker;

    private Member $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 09:00'));
        $this->asker = Accounts::signIn('email', 'asker@example.test', 'Asker');
        $this->travelTo(Carbon::parse('2026-10-12 09:00'));
        $this->helper = Accounts::signIn('email', 'helper@example.test', 'Helper');
    }

    private function at(string $time, callable $make)
    {
        $this->travelTo(Carbon::parse($time));

        return $make();
    }

    private function ask(string $time, string $type = 'question', string $status = Post::PUBLISHED): Post
    {
        return $this->at($time, fn () => Post::create(['member_id' => $this->asker->id, 'title' => "A {$type} at {$time}", 'type' => $type, 'status' => $status]));
    }

    private function answer(Post $post, string $time, ?Member $by = null, string $status = Post::PUBLISHED): Answer
    {
        return $this->at($time, fn () => Answer::create(['post_id' => $post->id, 'member_id' => ($by ?? $this->helper)->id, 'body' => 'An answer', 'status' => $status]));
    }

    public function test_time_to_first_answer(): void
    {
        $q1 = $this->ask('2026-10-11 10:00');
        $this->answer($q1, '2026-10-11 10:05', $this->asker); // the asker's own: doesn't count
        $this->answer($q1, '2026-10-11 10:30'); // 30 min
        $q2 = $this->ask('2026-10-12 09:00', 'help');
        $this->answer($q2, '2026-10-12 11:00'); // 2 h
        $this->answer($q2, '2026-10-12 11:30'); // a later one doesn't matter
        $q3 = $this->ask('2026-10-12 08:00');
        $this->answer($q3, '2026-10-13 10:00'); // 26 h
        $this->ask('2026-10-12 08:00'); // never answered
        $q7 = $this->ask('2026-10-12 08:00');
        $this->answer($q7, '2026-10-12 08:10', status: Post::HIDDEN); // taken down: still unanswered
        $tip = $this->ask('2026-10-12 08:00', 'tip');
        $this->answer($tip, '2026-10-12 08:01'); // not asking for an answer
        $this->ask('2026-10-14 11:30'); // half an hour old: too young for the 1 h / 24 h rates
        $this->ask('2026-10-12 08:00', 'question', Post::REMOVED); // not counted
        $this->ask('2026-10-01 08:00'); // before the range

        $this->travelTo(Carbon::parse(self::NOW));
        $stats = (new CommunityStats(now()->subDays(7)))->firstAnswers();

        $this->assertSame([
            'asked' => 6, 'answered' => 3, 'unanswered' => 3, 'median_minutes' => 120,
            'within_1h' => 20.0, // q1 of the 5 posts at least an hour old
            'within_24h' => 40.0, // q1 and q2 of the same 5
        ], $stats);
    }

    public function test_daily_growth_and_weekdays(): void
    {
        $this->ask('2026-10-12 08:00'); // Monday
        $this->ask('2026-10-12 23:59'); // still Monday in Dhaka
        $tuesday = $this->ask('2026-10-13 00:01');
        $this->answer($tuesday, '2026-10-13 09:00');
        $this->ask('2026-10-13 10:00', 'question', Post::DELETED); // the author's own deletion: left out
        $this->at('2026-10-13 12:00', fn () => Accounts::signIn('email', 'new@example.test', 'New'));

        $this->travelTo(Carbon::parse(self::NOW));
        $stats = new CommunityStats(Carbon::parse('2026-10-12 00:00'));

        $this->assertSame(['weekly' => false, 'rows' => [
            ['date' => '2026-10-12', 'members' => 1, 'posts' => 2, 'answers' => 0], // the helper signed up that morning
            ['date' => '2026-10-13', 'members' => 1, 'posts' => 1, 'answers' => 1],
            ['date' => '2026-10-14', 'members' => 0, 'posts' => 0, 'answers' => 0],
        ]], $stats->daily());
        $this->assertSame([2, 1, 0, 0, 0, 0, 0], array_column($stats->postsByWeekday(), 'posts'));
        $this->assertSame('Mon', $stats->postsByWeekday()[0]['day']);

        $this->assertTrue((new CommunityStats(now()->subDays(365)))->daily()['weekly']);
    }

    public function test_report_handling_from_the_moderation_log(): void
    {
        $admin = User::factory()->create();
        $reporters = array_map(fn ($i) => Accounts::signIn('email', "r{$i}@example.test", 'R'), range(1, 3));

        $post = $this->ask('2026-10-12 08:00');
        $this->at('2026-10-13 09:00', fn () => Moderation::report($reporters[0], $post->fresh(), 'spam'));
        $this->at('2026-10-13 10:30', fn () => $this->actingAs($admin)->post("/admin/community/post/{$post->id}/dismiss")); // 90 min
        $this->at('2026-10-13 11:00', fn () => $this->actingAs($admin)->post("/admin/community/post/{$post->id}/hide")); // no reports left: not a handling

        $answer = $this->answer($this->ask('2026-10-12 08:00'), '2026-10-12 09:00');
        $this->at('2026-10-13 12:00', fn () => Moderation::report($reporters[1], $answer->fresh(), 'abuse'));
        $this->at('2026-10-13 12:30', fn () => $this->actingAs($admin)->post("/admin/community/answer/{$answer->id}/remove")); // 30 min
        $this->at('2026-10-13 13:00', fn () => $this->actingAs($admin)->post("/admin/community/answer/{$answer->id}/restore")); // same report round: counted once

        $waiting = $this->ask('2026-10-12 08:00');
        $this->at('2026-10-14 10:00', fn () => Moderation::report($reporters[2], $waiting->fresh(), 'false'));

        $this->travelTo(Carbon::parse(self::NOW));
        Moderation::forgetNeedsLook();
        $this->assertSame([
            'handled' => 2, 'median_minutes' => 60,
            'waiting' => 3, // the hidden post, the restored answer (a restore keeps its reports) and the new one
            'oldest_waiting_minutes' => 1440, // the answer's report, yesterday at noon
        ], (new CommunityStats(now()->subDays(7)))->reportHandling());
    }

    public function test_the_dashboard_shows_the_panel(): void
    {
        $this->answer($this->ask('2026-10-13 10:00'), '2026-10-13 10:45');
        $this->travelTo(Carbon::parse(self::NOW));

        $this->actingAs(User::factory()->create())->get('/admin?days=7&fresh=1')->assertOk()
            ->assertSee('Community health')->assertSee('Time to first answer')->assertSee('45 min')
            ->assertSee('Report handling')->assertSee('Posts by weekday');
        $this->actingAs(User::factory()->create())->get('/admin?days=1&fresh=1')->assertOk()->assertSee('last 7 days');
    }
}
