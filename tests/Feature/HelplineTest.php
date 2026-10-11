<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\AdminAction;
use App\Models\Area;
use App\Models\Helpline;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class HelplineTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_the_starter_list_ships_switched_off_and_nothing_links_to_an_empty_page(): void
    {
        $this->assertSame(8, Helpline::count());
        $this->assertSame(0, Helpline::where('is_active', true)->orWhereNotNull('last_checked_at')->count());

        $this->get('/helplines')->assertOk()->assertSee('তালিকাটা এখনো যাচাই হচ্ছে')->assertDontSee('tel:', false);
        $this->get('/en/helplines')->assertOk()->assertSee('The list is still being checked');
        $this->get('/feed')->assertDontSee('href="/helplines"', false);
    }

    public function test_a_number_goes_public_only_after_it_is_checked_and_every_step_is_logged(): void
    {
        $this->get('/admin/helplines')->assertRedirect(); // admins only
        $admin = User::factory()->create();
        $h = Helpline::where('number', '999')->sole();

        // Unchecked: can't be turned on.
        $this->actingAs($admin)->put("/admin/helplines/{$h->id}", ['toggle' => 1, 'is_active' => 1])->assertSessionHasErrors('is_active');
        $this->assertFalse($h->fresh()->is_active);

        $this->actingAs($admin)->put("/admin/helplines/{$h->id}", ['checked' => 1])->assertRedirect('/admin/helplines');
        $this->assertTrue($h->fresh()->last_checked_at->isToday());
        $this->actingAs($admin)->put("/admin/helplines/{$h->id}", ['toggle' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($h->fresh()->is_active);
        $this->assertSame(['helpline_check', 'helpline_on'], AdminAction::where('target_type', 'helpline')->orderBy('id')->pluck('action')->all());

        // Public: tap to call, Bangla digits on Bangla pages, never the admin's source note.
        $h->update(['source' => 'গোপন নোট']);
        Helpline::forget();
        $this->get('/helplines')->assertSee('href="tel:999"', false)->assertSee('৯৯৯')->assertSee('জাতীয় জরুরি সেবা')
            ->assertSee('শেষ যাচাই')->assertDontSee('গোপন নোট')->assertDontSee('দুর্নীতি দমন কমিশন'); // still off
        $this->get('/en/helplines')->assertSee('National Emergency Service')->assertSee('>📞 999<', false)->assertSee('Emergency');

        // Links appear now that there is something to show.
        $this->get('/feed')->assertSee('href="/helplines"', false);
        $this->actingAs($admin)->get('/admin/helplines')->assertOk()->assertSee('Not checked');
        $this->actingAs($admin)->get('/admin/community/log')->assertOk()->assertSee('Helpline turned on');

        $this->actingAs($admin)->put("/admin/helplines/{$h->id}", ['toggle' => 1, 'is_active' => 0]);
        $this->get('/helplines')->assertDontSee('href="tel:999"', false);
    }

    public function test_an_admin_adds_an_area_number_and_the_area_filter_shows_it_with_the_nationwide_ones(): void
    {
        $admin = User::factory()->create();
        $district = Area::where('type', 'district')->firstOrFail();
        $division = Area::findOrFail($district->parent_id);
        $otherDivision = Area::where('type', 'division')->where('id', '!=', $division->id)->firstOrFail();
        Helpline::where('number', '999')->update(['is_active' => true, 'last_checked_at' => today()]);

        // Turned on without a check date: refused.
        $base = ['name_bn' => 'জেলা হাসপাতাল', 'name_en' => 'District hospital', 'number' => '+880 2-555 0101', 'kind' => 'health', 'area' => $district->slug];
        $this->actingAs($admin)->post('/admin/helplines', $base + ['is_active' => 1])->assertSessionHasErrors('is_active');
        $this->actingAs($admin)->post('/admin/helplines', array_merge($base, ['number' => '99<script>']))->assertSessionHasErrors('number');

        $this->actingAs($admin)->post('/admin/helplines', $base + ['is_active' => 1, 'last_checked_at' => today()->toDateString(), 'source' => 'Called'])
            ->assertRedirect('/admin/helplines');
        $new = Helpline::where('name_bn', 'জেলা হাসপাতাল')->sole();
        $this->assertSame($district->id, $new->area_id);
        $this->assertSame('helpline_add', AdminAction::where('target_id', $new->id)->sole()->action);

        $this->get('/helplines')->assertSee('জেলা হাসপাতাল')->assertSee('href="tel:+88025550101"', false);
        $this->get("/helplines?area={$district->slug}")->assertSee('জেলা হাসপাতাল')->assertSee('href="tel:999"', false);
        $this->get("/helplines?area={$division->slug}")->assertSee('জেলা হাসপাতাল'); // a division covers its districts
        $this->get("/helplines?area={$otherDivision->slug}")->assertDontSee('জেলা হাসপাতাল')->assertSee('href="tel:999"', false);

        // Editing: changes are logged with before/after.
        $this->actingAs($admin)->get("/admin/helplines/{$new->id}/edit")->assertOk();
        $this->actingAs($admin)->put("/admin/helplines/{$new->id}", array_merge($base, ['name_bn' => 'সদর হাসপাতাল', 'is_active' => 1, 'last_checked_at' => today()->toDateString()]))
            ->assertRedirect('/admin/helplines');
        $log = AdminAction::where('action', 'helpline_edit')->sole();
        $this->assertEquals(['from' => 'জেলা হাসপাতাল', 'to' => 'সদর হাসপাতাল'], $log->meta['changes']['name_bn']);
        $this->get('/helplines')->assertSee('সদর হাসপাতাল');
    }

    public function test_help_posts_and_the_ask_form_link_to_the_directory_for_the_posts_area(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Helpline::where('number', '999')->update(['is_active' => true, 'last_checked_at' => today()]);
        $district = Area::where('type', 'district')->firstOrFail();
        $member = Accounts::signIn('email', Str::random(10).'@example.test', 'রাশেদ');

        $help = $this->withHeader('X-Member-Token', $member->issueToken())
            ->postJson('/api/posts', ['type' => 'help', 'title' => 'রাতে হাসপাতালে কার সাথে যোগাযোগ করবো?', 'area' => $district->slug])->assertCreated()->json('post.id');
        $question = $this->postJson('/api/posts', ['type' => 'question', 'title' => 'ঢাকায় ভালো বইয়ের দোকান কোনটা?'])->assertCreated()->json('post.id');

        $this->get("/p/{$help}")->assertSee('href="/helplines?area='.$district->slug.'"', false);
        $this->get("/p/{$question}")->assertDontSee('জরুরি দরকারে হেল্পলাইন নম্বর দেখুন');
        $this->get('/ask')->assertSee('জীবন বা নিরাপত্তার ঝুঁকি থাকলে আগে হেল্পলাইনে ফোন করুন');
    }
}
