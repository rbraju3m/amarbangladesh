<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\User;
use App\Quiz\QuizConfig;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_can_log_in_and_see_every_page(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect('/admin');

        $question = Question::first();
        $location = Location::first();
        foreach (['/admin', '/admin?days=30', '/admin/balance', '/admin/plays', '/admin/plays?days=7&source=friend', '/admin/community', '/admin/community?show=all', '/admin/questions', "/admin/questions/{$question->id}/edit", '/admin/locations', "/admin/locations/{$location->slug}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_editing_a_question_updates_the_live_quiz(): void
    {
        $this->actingAs(User::factory()->create());
        $question = Question::with('options')->first();
        $this->get('/'); // warm the cache

        $options = $question->options->map(fn ($o) => [
            'id' => $o->id, 'label_bn' => $o->label_bn, 'emoji' => $o->emoji, 'reason_bn' => $o->reason_bn,
            'weights' => $o->trait_weights, 'bonus' => $o->location_bonus, 'is_active' => 1,
        ])->all();
        $options[] = ['label_bn' => '', 'reason_bn' => '']; // blank row is ignored

        $this->put("/admin/questions/{$question->id}", [
            'prompt_bn' => 'নতুন প্রশ্ন?', 'kind' => 'emoji', 'is_active' => 1, 'options' => $options,
        ])->assertRedirect();

        $this->assertSame(4, $question->options()->count());
        $this->get('/')->assertSee('নতুন প্রশ্ন?', false);
    }

    public function test_editing_a_location_profile(): void
    {
        $this->actingAs(User::factory()->create());
        $location = Location::first();

        $this->put("/admin/locations/{$location->slug}", [
            ...$location->only('name_bn', 'name_en', 'emoji', 'title_bn', 'tagline_bn', 'description_bn', 'reason_tail_bn', 'accent_color', 'sort_order'),
            'badges' => "🌿 এক\n☕ দুই",
            'profile' => ['nature' => 3] + $location->profile,
            'is_active' => 1,
        ])->assertRedirect();

        $location->refresh();
        $this->assertSame(['🌿 এক', '☕ দুই'], $location->badges);
        $this->assertSame(3, $location->profile['nature']);
    }

    public function test_english_content_edited_in_admin_shows_on_english_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $location = Location::first();

        $this->put("/admin/locations/{$location->slug}", [
            ...$location->only('name_bn', 'name_en', 'emoji', 'title_bn', 'tagline_bn', 'description_bn', 'reason_tail_bn', 'accent_color', 'sort_order'),
            'badges' => implode("\n", $location->badges),
            'title_en' => 'An edited English title',
            'badges_en' => "One\nTwo",
            'profile' => $location->profile,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(['One', 'Two'], $location->fresh()->badges_en);
        $this->get('/en')->assertSee('"title":"An edited English title"', false);
        $this->get('/')->assertSee('"title":"'.$location->title_bn.'"', false)->assertDontSee('An edited English title');
    }

    public function test_login_page_shows_a_clear_error_for_wrong_credentials(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass']);

        $this->from('/admin/login')->post('/admin/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('These credentials do not match.')->assertSee('toggle-password');
        $this->assertGuest();
    }

    public function test_admin_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $this->actingAs($user)->get('/admin/password')->assertOk()->assertSee($user->email);

        $this->from('/admin/password')->put('/admin/password', [
            'current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');

        $this->from('/admin/password')->put('/admin/password', [
            'current_password' => 'old-password', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/admin/password')->assertSessionHas('status');

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_questions_can_be_reordered_by_dragging(): void
    {
        $user = User::factory()->create();
        $ids = Question::orderBy('sort_order')->pluck('id')->all();
        $new = array_reverse($ids);

        $this->actingAs($user)->postJson(route('admin.questions.reorder'), ['ids' => $new])->assertOk();
        $this->assertSame($new, Question::orderBy('sort_order')->pluck('id')->all());
        $this->assertSame($new[0], array_key_first(QuizConfig::load()->questions), 'the live quiz uses the new order');

        // Partial or duplicated lists are refused and change nothing.
        $this->postJson(route('admin.questions.reorder'), ['ids' => array_slice($ids, 1)])->assertStatus(422);
        $this->postJson(route('admin.questions.reorder'), ['ids' => [$ids[0], ...array_slice($ids, 0, -1)]])->assertStatus(422);
        $this->assertSame($new, Question::orderBy('sort_order')->pluck('id')->all());
    }

    public function test_dashboard_lists_recent_plays_and_exports_csv(): void
    {
        $answers = array_map(fn ($o) => array_key_first($o), array_values(QuizConfig::fromDatabase()->questions));
        $code = $this->postJson('/api/results', ['answers' => $answers])->assertCreated()->json('result.code');
        $place = QuizResult::first()->location;
        $this->postJson('/api/events', ['events' => [['name' => 'place_opened', 'result' => $code, 'meta' => ['place' => $place->slug]]]])->assertNoContent();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertOk()
            ->assertSeeInOrder(['Recent plays', '/r/'.$code, $place->name_bn])
            ->assertSeeInOrder(['Places explored', $place->name_bn, '1']);

        $csv = $this->get(route('admin.export', ['type' => 'results', 'days' => 30]))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFtime,code,url,place,match_pct", $csv);
        $this->assertStringContainsString($code, $csv);
        $this->assertStringContainsString($place->name_en, $csv);

        $daily = $this->get(route('admin.export', ['type' => 'daily']))->assertOk()->streamedContent();
        $this->assertStringContainsString('date,visitors,completed,shared', $daily);
        $this->assertStringContainsString(now()->toDateString().',', $daily);

        $this->get('/admin/export/secrets.csv')->assertNotFound();
        auth()->logout();
        $this->get(route('admin.export', ['type' => 'results']))->assertRedirect(route('admin.login'));
    }

    public function test_plays_page_lists_every_play_with_filters(): void
    {
        $answers = array_map(fn ($o) => array_key_first($o), array_values(QuizConfig::fromDatabase()->questions));
        $first = $this->postJson('/api/results', ['answers' => $answers])->assertCreated()->json('result.code');
        $friend = $this->postJson('/api/results', ['answers' => $answers, 'ref' => $first])->assertCreated()->json('result.code');
        $place = QuizResult::first()->location;

        $this->actingAs(User::factory()->create());
        $this->get('/admin/plays')->assertOk()->assertSeeText('2 plays')->assertSeeInOrder(['/r/'.$friend, '/r/'.$first])
            ->assertSee(now()->format('j M Y'));
        $this->get('/admin/plays?source=friend')->assertSeeText('1 play')->assertSee('/r/'.$friend)->assertSee("Friend's link", false);
        $this->get('/admin/plays?source=direct&place='.$place->id)->assertSeeText('1 play')->assertDontSee('/r/'.$friend);
        $this->get('/admin/plays?place=999999')->assertSee('No plays match these filters.');
        $this->get('/admin')->assertSee(route('admin.plays', ['days' => 7]));
    }

    public function test_dashboard_shows_plays_by_hour(): void
    {
        $answers = array_map(fn ($o) => array_key_first($o), array_values(QuizConfig::fromDatabase()->questions));
        $this->postJson('/api/results', ['answers' => $answers])->assertCreated();
        $hour = now()->hour;

        $this->actingAs(User::factory()->create());
        $this->get('/admin?days=1')->assertOk()->assertSee('When people play')->assertSee('Busiest hour')
            ->assertSee(now()->format('D j M').', '.now()->setTime($hour, 0)->format('g a').' – ', false);
        $this->get('/admin?days=365')->assertOk()->assertSee(now()->format('l'));
    }

    public function test_editors_embed_the_live_balance_preview(): void
    {
        $user = User::factory()->create();
        $question = Question::first();
        $location = Location::first();

        $data = fn ($response) => json_decode(str($response->getContent())->between('id="balance-data">', '</script>'), true);

        $q = $data($this->actingAs($user)->get(route('admin.questions.edit', $question))->assertOk()->assertSee('data-balance-preview', false));
        $this->assertSame(['kind' => 'question', 'id' => $question->id], $q['ctx']);
        $this->assertCount(9, $q['meta']);
        $this->assertSame([6, 18], [(int) $q['min'], (int) $q['max']]);

        $l = $data($this->get(route('admin.locations.edit', $location))->assertOk());
        $this->assertSame(['kind' => 'location', 'slug' => $location->slug], $l['ctx']);
        $this->assertArrayHasKey($location->slug, $l['locations']);
    }

    public function test_dashboard_counts_card_designs(): void
    {
        $this->postJson('/api/events', ['events' => [
            ['name' => 'card_saved', 'meta' => ['template' => 'boarding', 'theme' => 'night', 'format' => 'square']],
            ['name' => 'card_saved', 'meta' => ['template' => 'boarding', 'theme' => 'night', 'format' => 'square']],
            ['name' => 'share_clicked', 'meta' => ['channel' => 'native', 'template' => 'poster']],
            ['name' => 'share_clicked', 'meta' => ['channel' => 'whatsapp']],
        ]])->assertNoContent();

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertSeeInOrder(['Card designs', 'Boarding pass', '2', 'Poster', '1', 'Square', '2', 'Story', '1'])
            ->assertSeeInOrder(['Card colours', 'Night', '2', 'Place colour', '1']);
    }
}
