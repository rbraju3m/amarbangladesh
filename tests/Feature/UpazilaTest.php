<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\DivisionMap;
use App\Community\Taxonomy;
use App\Models\Area;
use App\Models\Helpline;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpazilaTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_11_000008_add_upazilas_and_thanas_to_areas.php');
    }

    public function test_every_district_has_its_upazilas_after_migrate(): void
    {
        $this->assertSame(494, Area::where('type', 'upazila')->count());
        $this->assertSame(65, Area::where('type', 'thana')->count());

        $districts = Area::where('type', 'district')->withCount(['children' => fn ($q) => $q->where('type', 'upazila')])->get();
        $this->assertCount(64, $districts);
        $this->assertSame([], $districts->where('children_count', 0)->pluck('slug')->all(), 'a district without upazilas');

        $byDistrict = Taxonomy::upazilasByDistrict();
        $this->assertSame(['dhamrai', 'dohar', 'keraniganj', 'nawabganj', 'savar'], collect($byDistrict['dhaka'])->where('type', 'upazila')->map(fn ($a) => substr($a['slug'], 6))->sort()->values()->all());
        $this->assertCount(50, collect($byDistrict['dhaka'])->where('type', 'thana'));
        $this->assertCount(15, collect($byDistrict['chattogram'])->where('type', 'thana'));
    }

    public function test_upazilas_hang_under_districts_with_names_in_both_languages(): void
    {
        $areas = Area::whereIn('type', ['upazila', 'thana'])->with('parent')->get();

        foreach ($areas as $area) {
            $this->assertSame('district', $area->parent?->type, "{$area->slug} is not under a district");
            $this->assertStringStartsWith($area->parent->slug.'-', $area->slug);
            $this->assertMatchesRegularExpression('/^[\x{0980}-\x{09FF}\x{200C}\x{200D} ]+$/u', $area->name_bn, "{$area->slug} Bangla name");
            $this->assertMatchesRegularExpression("/^[A-Za-z][A-Za-z' .-]*$/", $area->name_en, "{$area->slug} English name");
        }

        // "X Sadar" reads exactly like its district.
        $sadar = Area::where('slug', 'cumilla-cumilla-sadar')->firstOrFail();
        $this->assertSame(['কুমিল্লা সদর', 'Cumilla Sadar'], [$sadar->name_bn, $sadar->name_en]);
    }

    public function test_each_area_knows_its_parents_and_what_lies_under_it(): void
    {
        $areas = Taxonomy::areas();

        $savar = $areas['dhaka-savar'];
        $this->assertSame(['upazila', 'dhaka', 'dhaka-division', 'ঢাকা', 'ঢাকা'], [$savar['type'], $savar['district_slug'], $savar['division_slug'], $savar['district'], $savar['division']]);
        $this->assertSame([$savar['id']], Taxonomy::areaIds('dhaka-savar'));

        $dhaka = Taxonomy::areaIds('dhaka');
        $this->assertContains($savar['id'], $dhaka);
        $this->assertContains($areas['dhaka-mirpur']['id'], $dhaka);
        $this->assertContains($areas['dhaka']['id'], $dhaka);
        $this->assertNotContains($areas['kushtia-mirpur']['id'], $dhaka, 'a same-named upazila elsewhere');

        $division = Taxonomy::areaIds('dhaka-division');
        $this->assertContains($areas['dhaka']['id'], $division);
        $this->assertContains($savar['id'], $division);
        $this->assertNotContains($areas['chattogram-kotwali']['id'], $division);

        $this->assertNull(Taxonomy::areaIds('nowhere'));
        $this->assertSame(['division', 'district', 'upazila'], array_values(array_unique(array_map(fn ($a) => $a['type'] === 'thana' ? 'upazila' : $a['type'], $areas))), 'widest first');
    }

    public function test_english_pages_get_english_names(): void
    {
        app()->setLocale('en');
        $savar = Taxonomy::areas()['dhaka-savar'];

        $this->assertSame(['Savar', 'Dhaka', 'Dhaka'], [$savar['name'], $savar['district'], $savar['division']]);
    }

    public function test_the_migration_can_run_again_and_be_undone(): void
    {
        $before = Area::count();
        $this->migration()->up();
        $this->assertSame($before, Area::count(), 'running again adds nothing');

        $this->migration()->down();
        $this->assertSame(0, Area::whereIn('type', ['upazila', 'thana'])->count());
        $this->assertSame(72, Area::count(), 'divisions and districts stay');
        $this->assertArrayNotHasKey('dhaka-savar', Taxonomy::areas());
    }

    private function postIn(string $area, string $title): Post
    {
        $member = Accounts::signIn('email', 'upazila@example.test', 'Rashed');

        return Post::create(['member_id' => $member->id, 'title' => $title, 'area_id' => Taxonomy::areas()[$area]['id']]);
    }

    public function test_an_upazila_post_shows_wherever_its_district_does(): void
    {
        $this->postIn('dhaka-savar', 'Savar road question');
        $this->postIn('dhaka-mirpur', 'Mirpur school question');
        $this->postIn('kushtia-mirpur', 'Kushtia Mirpur question');

        $this->get('/feed?area=dhaka-savar')->assertOk()->assertSee('Savar road question')->assertDontSee('Mirpur school question')
            ->assertSee('সাভার, ঢাকা')->assertSee('ঢাকা জেলার সব আলোচনা')->assertSee('area=dhaka', false);
        $this->get('/feed?area=dhaka')->assertOk()->assertSee('📍 সাভার, ঢাকা')->assertSee('Savar road question')->assertSee('Mirpur school question')->assertDontSee('Kushtia Mirpur question');
        $this->get('/feed?area=dhaka-division')->assertOk()->assertSee('Savar road question')->assertDontSee('Kushtia Mirpur question');
        $this->get('/feed')->assertOk()->assertSee('Savar road question')->assertSee('Kushtia Mirpur question');
        $this->get('/feed?area=dhaka-mirpur')->assertOk()->assertSee('Mirpur school question')->assertDontSee('Kushtia Mirpur question');

        $this->getJson('/api/feed?area=dhaka')->assertOk()->assertSee('Savar road question');
        $this->get('/en/feed?area=dhaka-savar')->assertOk()->assertSee('Savar, Dhaka')->assertSee('Everything in Dhaka district');
    }

    public function test_the_map_counts_upazila_posts_in_their_district_and_division(): void
    {
        $this->postIn('moulvibazar-sreemangal', 'Tea garden question');
        $this->postIn('sylhet', 'City question');

        $this->assertSame(2, DivisionMap::counts('division')['all']['sylhet-division']);
        $this->assertSame(1, DivisionMap::counts('district')['all']['moulvibazar'] ?? null);
        $this->assertSame('Tea garden question', DivisionMap::latest('district')['moulvibazar'] ?? null);

        $districts = collect(DivisionMap::division('sylhet-division')['districts'])->keyBy('slug');
        $this->assertSame(1, $districts['moulvibazar']['count']);
    }

    public function test_helplines_for_an_upazila_include_its_district_and_division(): void
    {
        $areas = Taxonomy::areas();
        $add = fn (string $name, ?string $area) => Helpline::create([
            'name_bn' => $name, 'number' => '1'.strlen($name), 'kind' => array_key_first(Helpline::KINDS), 'area_id' => $area ? $areas[$area]['id'] : null,
            'last_checked_at' => today(), 'is_active' => true,
        ]);
        $add('Nationwide', null);
        $add('Dhaka division line', 'dhaka-division');
        $add('Dhaka district line', 'dhaka');
        $add('Savar line', 'dhaka-savar');
        $add('Dhamrai line', 'dhaka-dhamrai');
        $add('Sylhet line', 'sylhet');
        Helpline::forget();

        $names = fn (string $area) => collect(Helpline::forArea($areas[$area]))->pluck('name_bn')->sort()->values()->all();
        $this->assertSame(['Dhaka district line', 'Dhaka division line', 'Nationwide', 'Savar line'], $names('dhaka-savar'));
        $this->assertSame(['Dhaka district line', 'Dhaka division line', 'Dhamrai line', 'Nationwide', 'Savar line'], $names('dhaka'));
        $this->assertContains('Savar line', $names('dhaka-division'));
        $this->assertNotContains('Savar line', $names('sylhet'));

        $this->get('/helplines?area=dhaka-savar')->assertOk()->assertSee('Savar line')->assertSee('সাভার, ঢাকা')->assertDontSee('Dhamrai line');
    }

    public function test_post_tags_name_the_upazila_with_its_district(): void
    {
        $post = $this->postIn('dhaka-savar', 'Savar road question');

        $this->get("/p/{$post->id}")->assertOk()->assertSee('📍 সাভার, ঢাকা')->assertSee('area=dhaka-savar', false);
        $this->get('/')->assertOk()->assertSee('সাভার, ঢাকা');
    }

    private function ask(array $data)
    {
        $member = Accounts::signIn('email', 'asker@example.test', 'Rashed');

        return $this->withHeader('X-Member-Token', $member->issueToken())
            ->postJson('/api/posts', $data + ['title' => 'Where is a good clinic around here?']);
    }

    public function test_a_post_can_name_an_upazila_in_its_district(): void
    {
        $id = $this->ask(['area' => 'dhaka', 'upazila' => 'dhaka-savar'])->assertCreated()->json('post.id');
        $this->assertSame(Taxonomy::areas()['dhaka-savar']['id'], Post::find($id)->area_id);

        $id = $this->ask(['area' => 'dhaka', 'upazila' => '', 'title' => 'A question about the whole district'])->assertCreated()->json('post.id');
        $this->assertSame(Taxonomy::areas()['dhaka']['id'], Post::find($id)->area_id, 'no upazila: the district');

        $id = $this->ask(['upazila' => 'dhaka-mirpur', 'title' => 'An upazila sent on its own'])->assertCreated()->json('post.id');
        $this->assertSame(Taxonomy::areas()['dhaka-mirpur']['id'], Post::find($id)->area_id);
    }

    public function test_an_upazila_from_another_district_or_a_district_as_upazila_is_refused(): void
    {
        $this->ask(['area' => 'sylhet', 'upazila' => 'dhaka-savar'])->assertUnprocessable()->assertJsonValidationErrors('upazila');
        $this->ask(['area' => 'dhaka', 'upazila' => 'dhaka'])->assertUnprocessable()->assertJsonValidationErrors('upazila');
        $this->assertSame(0, Post::count());
    }

    public function test_the_ask_form_offers_each_districts_upazilas_and_fills_them_from_a_link(): void
    {
        $page = $this->get('/ask')->assertOk()->assertSee('name="upazila"', false);
        $this->assertStringContainsString('dhaka-savar', $page->getContent());

        // From an upazila's feed: both pickers start filled.
        $html = $this->get('/ask?area=dhaka-savar')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="dhaka" selected/', $html);
        $this->assertStringContainsString("upazila: 'dhaka-savar'", $html);

        $this->get('/en/ask')->assertOk()->assertSee('Savar')->assertSee('Upazila or thana');
    }

    public function test_the_feed_offers_a_districts_upazilas_as_links(): void
    {
        $this->get('/feed?area=dhaka')->assertOk()->assertSee('area=dhaka-savar', false)->assertSee('মিরপুর (থানা)')->assertSee('পুরো জেলা');
        $this->get('/feed?area=dhaka-savar')->assertOk()->assertSee('area=dhaka-dhamrai', false)->assertSee('aria-current="page"', false);
        $this->get('/feed?area=dhaka-division')->assertOk()->assertDontSee('area=dhaka-savar', false);
        $this->get('/feed')->assertOk()->assertDontSee('area=dhaka-savar', false);
    }

    public function test_admin_editors_can_pick_an_upazila(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/helplines')->assertOk()->assertSee('value="dhaka-savar"', false);
    }
}
