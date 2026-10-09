<?php

namespace Tests\Feature;

use App\Analytics\Funnel;
use App\Community\Accounts;
use App\Community\Taxonomy;
use App\Models\AnalyticsEvent;
use App\Models\Location;
use App\Models\Member;
use App\Models\Post;
use App\Models\User;
use App\Quiz\QuizConfig;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Tests\TestCase;

/** The quiz → community journey: tying community steps to the anonymous visitor, and the dashboard panel. */
class GrowthTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function play(string $visitor): void
    {
        $answers = array_map(fn ($o) => array_keys($o)[0], array_values(QuizConfig::fromDatabase()->questions));
        $this->postJson('/api/results', ['answers' => $answers, 'visitor_id' => $visitor])->assertCreated();
    }

    private function event(string $visitor, string $name): void
    {
        $this->postJson('/api/events', ['visitor_id' => $visitor, 'events' => [['name' => $name]]])->assertNoContent();
    }

    /** Signs up by email from this visitor's browser; returns the token. */
    private function signUp(string $visitor): string
    {
        return $this->withHeader('X-Visitor', $visitor)->postJson('/api/auth/email/register', [
            'name' => 'রাশেদ', 'email' => Str::random(8).'@example.test', 'password' => 'secret-pass-1',
        ])->assertCreated()->json('token');
    }

    public function test_sign_ups_posts_and_answers_carry_the_visitor_id(): void
    {
        $visitor = (string) Str::uuid();
        $token = $this->signUp($visitor);
        $post = $this->withHeaders(['X-Visitor' => $visitor, 'X-Member-Token' => $token])
            ->postJson('/api/posts', ['title' => 'সিলেটে বেড়াতে কোন মাসে যাওয়া ভালো?'])->assertCreated()->json('post.id');
        $this->postJson("/api/posts/{$post}/answers", ['body' => 'অক্টোবর থেকে মার্চ।'])->assertCreated();

        foreach (['signed_up', 'post_created', 'answer_created'] as $name) {
            $this->assertSame($visitor, AnalyticsEvent::where('name', $name)->value('visitor_id'), $name);
        }
        $this->assertSame(['method' => 'email'], AnalyticsEvent::where('name', 'signed_up')->value('meta'));

        // Logging in again is not a sign-up; a malformed id is dropped.
        $this->withHeader('X-Visitor', 'not-a-uuid')->postJson('/api/auth/email/login', ['email' => Member::first()->email, 'password' => 'secret-pass-1'])->assertOk();
        $this->assertSame(1, AnalyticsEvent::where('name', 'signed_up')->count());
    }

    public function test_google_sign_up_brings_the_visitor_id_through_the_round_trip(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        Socialite::fake('google', (new SocialUser)->map(['id' => 'g-1', 'name' => 'Nadia', 'email' => 'nadia@example.com']));
        $visitor = (string) Str::uuid();

        $this->get("/auth/google/redirect?v={$visitor}")->assertRedirect();
        $this->get('/auth/google/callback')->assertRedirect();
        $this->get('/auth/google/callback')->assertRedirect(); // signing in again: no second sign-up

        $this->assertSame(1, AnalyticsEvent::where('name', 'signed_up')->count());
        $this->assertSame($visitor, AnalyticsEvent::where('name', 'signed_up')->value('visitor_id'));
        $this->assertSame(['method' => 'google'], AnalyticsEvent::where('name', 'signed_up')->value('meta'));
    }

    public function test_the_quiz_to_community_funnel_counts_people_step_by_step(): void
    {
        [$a, $b, $c, $d] = array_map(fn () => (string) Str::uuid(), range(1, 4));
        foreach ([$a, $b, $c] as $v) {
            $this->play($v);
        }
        $this->play($a); // a replay is still one person

        // a: the whole way; b: clicked and looked; c: only played; d: came to the community without the quiz.
        foreach ([$a, $b] as $v) {
            $this->event($v, 'community_clicked');
            $this->event($v, 'feed_view');
        }
        $token = $this->signUp($a);
        $this->withHeaders(['X-Visitor' => $a, 'X-Member-Token' => $token])->postJson('/api/posts', ['title' => 'বান্দরবানে প্রথমবার, কোথায় থাকবো?'])->assertCreated();
        $this->event($d, 'post_view');
        $this->signUp($d);

        $community = (new Funnel(now()->subDay()))->community();

        $this->assertSame([3, 2, 2, 1, 1], array_column($community['steps'], 'count'));
        $this->assertSame(3, $community['viewers']);
        $this->assertSame(2, $community['viewers_played']);
        $this->assertSame(2, $community['signups']);
        $this->assertSame(1, $community['writers']);
        $this->assertSame(1, $community['writers_played']);
    }

    public function test_returning_visitors_were_seen_on_two_days(): void
    {
        [$once, $twice] = [(string) Str::uuid(), (string) Str::uuid()];
        $this->event($once, 'landing_view');
        $this->event($once, 'feed_view');
        $this->event($twice, 'landing_view');
        $this->travel(1)->days();
        $this->event($twice, 'feed_view');

        $this->assertSame(1, (new Funnel(now()->subDays(7)))->returningVisitors());
    }

    public function test_the_dashboard_shows_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertSee('Quiz → community')->assertSee('Returning visitors');
    }

    public function test_every_place_points_at_a_community_area(): void
    {
        $this->assertSame('khulna-division', Location::where('slug', 'sundarbans')->value('area_slug'));
        $this->assertSame('dhaka', Location::where('slug', 'puran-dhaka')->value('area_slug'));
        $this->assertSame(0, Location::whereNull('area_slug')->count());

        // The quiz page's boot data carries it, for the result page's links.
        $this->get('/')->assertOk()->assertSee('"area":"bandarban"', false);
    }

    public function test_the_area_can_be_changed_in_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $location = Location::where('slug', 'sylhet')->first();
        $payload = fn (?string $area) => [
            ...$location->only('name_bn', 'name_en', 'emoji', 'title_bn', 'tagline_bn', 'description_bn', 'reason_tail_bn', 'accent_color', 'sort_order'),
            'badges' => "🌿 এক\n☕ দুই", 'profile' => $location->profile, 'is_active' => 1, 'area_slug' => $area,
        ];

        $this->get("/admin/locations/{$location->slug}/edit")->assertOk()->assertSee('Community area');
        $this->put("/admin/locations/{$location->slug}", $payload('sylhet-division'))->assertRedirect();
        $this->assertSame('sylhet-division', $location->fresh()->area_slug);
        $this->put("/admin/locations/{$location->slug}", $payload('atlantis'))->assertSessionHasErrors('area_slug');
        $this->put("/admin/locations/{$location->slug}", $payload(null))->assertRedirect();
        $this->assertNull($location->fresh()->area_slug);
    }

    public function test_place_previews_come_from_the_places_area(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'রাশেদ');
        $areas = Taxonomy::areas();
        Post::create(['member_id' => $member->id, 'title' => 'বান্দরবানে কোন ঝর্ণাটা সবচেয়ে সুন্দর?', 'area_id' => $areas['bandarban']['id']]);
        Post::create(['member_id' => $member->id, 'title' => 'ঢাকায় ভালো ডেন্টিস্ট কোথায়?', 'area_id' => $areas['dhaka']['id']]);

        $html = $this->getJson('/api/feed?limit=2&compact=1&area=bandarban')->assertJson(['count' => 1])->json('html');
        $this->assertStringContainsString('ঝর্ণা', $html);
        $this->getJson('/api/feed?limit=2&compact=1&area=sylhet')->assertJson(['count' => 0]); // the page then falls back to the latest
    }

    public function test_clicks_into_a_place_are_counted_by_place(): void
    {
        [$a, $b] = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ([[$a, 'bandarban'], [$a, 'bandarban'], [$b, 'bandarban'], [$b, 'sylhet']] as [$v, $place]) {
            $this->postJson('/api/events', ['visitor_id' => $v, 'events' => [['name' => 'community_clicked', 'meta' => ['from' => 'result', 'to' => 'feed', 'place' => $place]]]])->assertNoContent();
        }

        $this->assertSame([['name' => 'বান্দরবান', 'people' => 2], ['name' => 'সিলেট', 'people' => 1]], (new Funnel(now()->subDay()))->communityClicksByPlace());
    }

    public function test_the_quiz_promo_links_to_the_quiz_in_the_pages_language(): void
    {
        $this->get('/en/feed')->assertOk()->assertSee('href="/en" @click="track(\'community_clicked\'', false);
        $this->get('/feed')->assertOk()->assertSee('href="/" @click="track(\'community_clicked\'', false);
    }
}
