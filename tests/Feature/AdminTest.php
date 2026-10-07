<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Question;
use App\Models\User;
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
        foreach (['/admin', '/admin?days=30', '/admin/balance', '/admin/questions', "/admin/questions/{$question->id}/edit", '/admin/locations', "/admin/locations/{$location->slug}/edit"] as $url) {
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
