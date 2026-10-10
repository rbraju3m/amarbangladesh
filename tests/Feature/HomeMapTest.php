<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\DivisionMap;
use App\Community\Taxonomy;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeMapTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_every_district_has_a_shape_inside_its_division(): void
    {
        $shapes = require resource_path('data/bd-districts.php');
        $districts = array_filter(Taxonomy::areas(), fn ($a) => $a['type'] === 'district');
        $this->assertCount(64, $shapes);
        $this->assertSame([], array_values(array_diff(array_keys($districts), array_keys($shapes))));

        foreach (array_keys(DivisionMap::POSITIONS) as $division) {
            $map = DivisionMap::division($division);
            [$x, $y, $w, $h] = $map['view'];
            $this->assertEqualsWithDelta(DivisionMap::WIDTH / DivisionMap::HEIGHT, $w / $h, 0.01, "$division keeps the map's proportions");
            foreach ($map['districts'] as $d) {
                $this->assertTrue($d['x'] > $x && $d['x'] < $x + $w && $d['y'] > $y && $d['y'] < $y + $h, "{$d['slug']} is in view");
            }
        }
    }

    public function test_the_map_zooms_into_a_division_with_counts_per_district(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $areas = Taxonomy::areas();
        foreach (['sylhet', 'sylhet', 'moulvibazar', 'sylhet-division'] as $area) {
            Post::create(['member_id' => $member->id, 'title' => "A question about $area", 'area_id' => $areas[$area]['id']]);
        }
        Post::create(['member_id' => $member->id, 'title' => 'Hidden', 'area_id' => $areas['habiganj']['id'], 'status' => Post::HIDDEN]);

        $response = $this->getJson('/api/map/sylhet-division')->assertOk();
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertEmpty($response->headers->getCookies());
        $districts = collect($response->json('districts'))->keyBy('slug');
        $this->assertSame(['habiganj', 'moulvibazar', 'sunamganj', 'sylhet'], $districts->keys()->sort()->values()->all());
        $this->assertSame('সিলেট জেলা', $districts['sylhet']['name']);
        $this->assertStringStartsWith('২টি আলোচনা', $districts['sylhet']['label']);
        $this->assertStringStartsWith('০টি আলোচনা', $districts['habiganj']['label']); // hidden posts don't count
        $this->assertSame('/feed?area=sylhet', $districts['sylhet']['url']);

        $html = $response->json('html');
        $this->assertSame(4, substr_count($html, 'data-district='));
        $this->assertStringContainsString('href="/feed?area=sylhet" class="district-link"', $html); // plain links without JS
        $this->assertStringContainsString('class="district level-4"', $html); // the busiest district
        $this->assertStringContainsString('class="district level-0"', $html);
        $this->assertStringNotContainsString('x-', $html); // no Alpine in fetched HTML

        // The division still counts its own (division-level) post; districts don't.
        $this->assertSame(4, DivisionMap::counts()['all']['sylhet-division']);
        $this->assertArrayNotHasKey('sylhet-division', DivisionMap::counts('district')['all']);

        $en = $this->getJson('/api/map/sylhet-division?lang=en')->assertOk();
        $this->assertSame('Sylhet District', collect($en->json('districts'))->firstWhere('slug', 'sylhet')['name']);
        $this->assertSame('/en/feed?area=sylhet', collect($en->json('districts'))->firstWhere('slug', 'sylhet')['url']);

        $this->getJson('/api/map/sylhet')->assertNotFound(); // a district is not a division
        $this->getJson('/api/map/nowhere')->assertNotFound();
    }

    public function test_counts_by_category(): void
    {
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $areas = Taxonomy::areas();
        $health = Taxonomy::categories()['health']['id'];
        Post::create(['member_id' => $member->id, 'title' => 'Doctor?', 'area_id' => $areas['sylhet']['id'], 'category_id' => $health]);
        Post::create(['member_id' => $member->id, 'title' => 'Tea?', 'area_id' => $areas['sylhet']['id']]);

        $this->assertSame(2, DivisionMap::counts()['all']['sylhet-division']);
        $this->assertSame(['sylhet-division' => 1], DivisionMap::counts('division', $health)['all']);
    }
}
