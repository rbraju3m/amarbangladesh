<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\Area;
use App\Models\Member;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomeDistrictTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{Member, string} */
    private function member(): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', 'রাশেদ');

        return [$member, $member->issueToken()];
    }

    public function test_a_member_sets_changes_and_clears_their_home_district(): void
    {
        [$member, $token] = $this->member();
        $district = Area::where('type', 'district')->firstOrFail();
        $division = Area::findOrFail($district->parent_id);
        $me = fn () => $this->withHeader('X-Member-Token', $token);

        $me()->getJson('/api/members/me')->assertJsonPath('member.district', null);

        $me()->patchJson('/api/members/me', ['district' => $district->slug])->assertOk()
            ->assertJsonPath('member.district.slug', $district->slug)
            ->assertJsonPath('member.district.division', $division->slug)
            ->assertJsonPath('member.district.name_bn', $district->name_bn)
            ->assertJsonPath('member.district.name_en', $district->name_en)
            ->assertJsonPath('member.name', 'রাশেদ'); // the name stays
        $this->assertSame($district->id, $member->fresh()->district_id);
        $me()->getJson('/api/members/me')->assertJsonPath('member.district.slug', $district->slug);

        // Districts only: a division or an unknown slug is refused.
        $me()->patchJson('/api/members/me', ['district' => $division->slug])->assertJsonValidationErrors('district');
        $me()->patchJson('/api/members/me', ['district' => 'nowhere'])->assertJsonValidationErrors('district');

        // Renaming leaves the district alone; an empty district clears it.
        $me()->patchJson('/api/members/me', ['name' => 'রাশেদা'])->assertOk()->assertJsonPath('member.district.slug', $district->slug);
        $me()->patchJson('/api/members/me', ['district' => ''])->assertOk()->assertJsonPath('member.district', null);
        $this->assertNull($member->fresh()->district_id);
        $me()->patchJson('/api/members/me', [])->assertJsonValidationErrors('name');

        $this->flushHeaders()->patchJson('/api/members/me', ['district' => $district->slug])->assertUnauthorized();
    }

    public function test_it_stays_private_and_goes_with_the_account(): void
    {
        [$member, $token] = $this->member();
        $district = Area::where('type', 'district')->firstOrFail();
        $member->update(['district_id' => $district->id]);
        $this->assertArrayNotHasKey('district_id', $member->fresh()->toArray());

        // Public pages are the same for everyone: the member's choice isn't in them.
        $this->get("/u/{$member->code}")->assertOk()->assertSee('id="home-area"', false)
            ->assertSee('<option value="'.$district->slug.'">', false); // not pre-selected: the browser fills it in
        $this->get('/feed')->assertOk()->assertSee(':href="nearUrl"', false);
        $this->get('/')->assertOk()->assertSee('pickHome()', false)->assertSee('myDistrict?.division ===', false);

        $this->withHeader('X-Member-Token', $token)->deleteJson('/api/members/me', ['confirm' => $member->code])->assertNoContent();
        $this->assertNull($member->fresh()->district_id);
    }

    public function test_the_privacy_page_mentions_it(): void
    {
        $this->get('/privacy')->assertSee('বেছে নিলে আপনার জেলা');
        $this->get('/en/privacy')->assertSee('your district (only you can see these two)');
    }
}
