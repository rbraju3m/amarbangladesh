<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_an_admin_adds_one_and_every_public_page_shows_it_in_its_language(): void
    {
        $this->get('/admin/announcements')->assertRedirect(); // admins only
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/admin/announcements', [
            'text_bn' => 'বন্যার খবর এখানে', 'text_en' => 'Flood news here', 'link' => '/feed?category=health',
            'level' => 'warning', 'starts_at' => '', 'ends_at' => now()->addDay()->format('Y-m-d\TH:i'), 'is_active' => 1,
        ])->assertRedirect('/admin/announcements')->assertSessionHas('status', fn ($s) => str_contains($s, 'shows now'));
        $a = Announcement::sole();
        $this->assertSame('announce_add', AdminAction::where('target_type', 'announcement')->sole()->action);

        foreach (['/', '/quiz', '/feed', '/ask', '/privacy'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-announcement="'.$a->id.'"', false)->assertSee('বন্যার খবর এখানে')
                ->assertSee('href="/feed?category=health"', false);
        }
        $this->get('/en/feed')->assertSee('Flood news here')->assertDontSee('বন্যার খবর এখানে')
            ->assertSee('href="/en/feed?category=health"', false)->assertSee('Close this announcement');

        // No English text: English pages fall back to the Bangla.
        $a->update(['text_en' => null]);
        Announcement::forget();
        $this->get('/en/quiz')->assertSee('বন্যার খবর এখানে');

        $this->actingAs($admin)->get('/admin/announcements')->assertOk()->assertSee('Showing now')->assertSee('Live');
    }

    public function test_only_what_is_on_and_inside_its_time_window_shows_warnings_first(): void
    {
        $info = Announcement::create(['text_bn' => 'সাধারণ খবর', 'level' => 'info']);
        $later = Announcement::create(['text_bn' => 'পরের খবর', 'level' => 'warning', 'starts_at' => now()->addHours(2)]);
        Announcement::create(['text_bn' => 'পুরনো খবর', 'level' => 'warning', 'ends_at' => now()->subMinute()]);
        Announcement::create(['text_bn' => 'বন্ধ খবর', 'level' => 'warning', 'is_active' => false]);

        $this->assertSame($info->id, Announcement::current()->id);
        $this->get('/')->assertSee('সাধারণ খবর')->assertDontSee('পরের খবর')->assertDontSee('পুরনো খবর')->assertDontSee('বন্ধ খবর');

        // Its start time passes: shown with no cache flush, and the warning wins over the info.
        $this->travel(3)->hours();
        $this->assertSame($later->id, Announcement::current()->id);
        $this->get('/feed')->assertSee('পরের খবর')->assertDontSee('সাধারণ খবর');

        Announcement::query()->update(['is_active' => false]);
        Announcement::forget();
        $this->assertNull(Announcement::current());
        $this->get('/')->assertDontSee('data-announcement=', false);
    }

    public function test_edit_and_toggle_are_logged_and_bad_input_is_refused(): void
    {
        $admin = User::factory()->create();
        $a = Announcement::create(['text_bn' => 'প্রথম লেখা', 'level' => 'info']);
        $this->get('/')->assertSee('প্রথম লেখা'); // caches the live list

        $this->actingAs($admin)->get("/admin/announcements/{$a->id}/edit")->assertOk()->assertSee('প্রথম লেখা');
        $this->actingAs($admin)->put("/admin/announcements/{$a->id}", ['text_bn' => 'নতুন লেখা', 'level' => 'info', 'is_active' => 1])->assertRedirect();
        $edit = AdminAction::where('action', 'announce_edit')->sole();
        $this->assertEquals(['from' => 'প্রথম লেখা', 'to' => 'নতুন লেখা'], $edit->meta['changes']['text_bn']);
        $this->get('/')->assertSee('নতুন লেখা'); // the cache was cleared

        $this->actingAs($admin)->put("/admin/announcements/{$a->id}", ['text_bn' => 'নতুন লেখা', 'level' => 'info', 'is_active' => 1])
            ->assertSessionHas('status', 'Nothing changed.');

        // The list's quick switch changes only is_active.
        $this->actingAs($admin)->put("/admin/announcements/{$a->id}", ['toggle' => 1, 'is_active' => 0])->assertRedirect();
        $this->assertFalse($a->fresh()->is_active);
        $this->assertSame('নতুন লেখা', $a->fresh()->text_bn);
        $this->assertSame(1, AdminAction::where('action', 'announce_off')->count());
        $this->get('/')->assertDontSee('নতুন লেখা');
        $this->actingAs($admin)->get('/admin/community/log')->assertSee('Announcement turned off')->assertSee("/admin/announcements/{$a->id}/edit", false);

        $base = ['text_bn' => 'x', 'level' => 'info', 'is_active' => 1];
        $this->actingAs($admin)->post('/admin/announcements', $base + ['link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
        $this->actingAs($admin)->post('/admin/announcements', $base + ['link' => '//evil.example'])->assertSessionHasErrors('link');
        $this->actingAs($admin)->post('/admin/announcements', array_merge($base, ['level' => 'party']))->assertSessionHasErrors('level');
        $this->actingAs($admin)->post('/admin/announcements', $base + ['starts_at' => '2026-10-12T10:00', 'ends_at' => '2026-10-12T09:00'])->assertSessionHasErrors('ends_at');
        $this->actingAs($admin)->post('/admin/announcements', $base + ['link' => 'https://dghs.gov.bd', 'starts_at' => '2026-10-12T10:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-12 10:00', Announcement::latest('id')->first()->starts_at->format('Y-m-d H:i')); // Dhaka time as typed
    }
}
