<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Models\AdminAction;
use App\Models\Member;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Verification badges (feature plan chunk 11): contact badges from identities, professional badges from admins only. */
class BadgesTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function write(Member $member, array $data): int
    {
        return $this->withHeader('X-Member-Token', $member->issueToken())->postJson('/api/posts', $data)->assertCreated()->json('post.id');
    }

    /** The post itself, without the related posts under it (they may be the same author's named posts). */
    private function postHtml(int $id): string
    {
        $html = $this->get("/p/{$id}")->assertOk()->getContent();
        $start = strpos($html, 'data-post-view="'.$id.'"');

        return substr($html, $start, strpos($html, '</article>', $start) - $start);
    }

    public function test_contact_badges_come_from_how_a_member_signs_in(): void
    {
        $phone = Accounts::signIn('phone', '+8801712345678', 'ফোনের মানুষ');
        $google = Accounts::signIn('google', 'g-'.Str::random(8), 'গুগলের মানুষ');
        $facebook = Accounts::signIn('facebook', 'f-'.Str::random(8), 'ফেসবুকের মানুষ');

        $this->assertSame(['phone'], $phone->contactBadges());
        $this->assertSame(['email'], $google->contactBadges());
        $this->assertSame([], $facebook->contactBadges());

        $this->get("/u/{$phone->code}")->assertSee('ফোন নম্বর যাচাই করা')->assertSee('পরিচয় যাচাই করা না')->assertDontSee('+8801712345678');
        $this->get("/en/u/{$google->code}")->assertSee('Email verified');
        $this->get("/u/{$facebook->code}")->assertDontSee('যাচাই করা');

        // On posts where trust matters, not on an ordinary question; never on an anonymous one.
        $help = $this->write($phone, ['type' => 'help', 'title' => 'বাবার অপারেশনের জন্য সাহায্য দরকার']);
        $question = $this->write($phone, ['type' => 'question', 'title' => 'ঢাকা থেকে সিলেটের ট্রেন কখন ছাড়ে?']);
        $anon = $this->write($phone, ['type' => 'help', 'title' => 'বেনামে একটা সাহায্য চাইছি এখানে', 'anonymous' => 1]);
        $this->get("/p/{$help}")->assertSee('ফোন নম্বর যাচাই করা');
        $this->get("/p/{$question}")->assertDontSee('ফোন নম্বর যাচাই করা');
        $this->assertStringContainsString('বেনামী', $this->postHtml($anon));
        $this->assertStringNotContainsString('ফোন নম্বর যাচাই করা', $this->postHtml($anon));
        $this->assertStringNotContainsString('ফোনের মানুষ', $this->postHtml($anon));
    }

    public function test_admins_give_change_and_remove_a_professional_badge_and_it_is_logged(): void
    {
        $admin = User::factory()->create();
        $doctor = Accounts::signIn('email', 'doc@example.test', 'ডা. রুমানা');
        $doctor->forceFill(['code' => 'drrumana1'])->save();
        $named = $this->write($doctor, ['type' => 'question', 'title' => 'শিশুর জ্বর হলে কখন হাসপাতালে নেবেন?']);
        $anon = $this->write($doctor, ['type' => 'question', 'title' => 'বেনামে একটা প্রশ্ন করছি এখানে', 'anonymous' => 1]);

        // A note is required; a profile link works as well as a code.
        $this->actingAs($admin)->post('/admin/badges', ['member' => 'https://example.test/en/u/drrumana1/', 'badge_bn' => 'ডাক্তার', 'badge_en' => 'Doctor'])
            ->assertSessionHasErrors('badge_note');
        $this->actingAs($admin)->post('/admin/badges', ['member' => 'nobody', 'badge_bn' => 'ডাক্তার', 'badge_note' => 'x'])->assertSessionHasErrors('member');
        $this->actingAs($admin)->post('/admin/badges', [
            'member' => 'https://example.test/en/u/drrumana1/', 'badge_bn' => ' ডাক্তার ', 'badge_en' => 'Doctor', 'badge_note' => 'BMDC A-12345 checked online',
        ])->assertRedirect('/admin/badges');

        $doctor->refresh();
        $this->assertSame('ডাক্তার', $doctor->badge_bn);
        $this->assertSame($admin->id, $doctor->badge_by);
        $this->assertNotNull($doctor->badge_at);
        $log = AdminAction::where('action', 'badge_give')->sole();
        $this->assertSame(['member', $doctor->id, 'BMDC A-12345 checked online'], [$log->target_type, $log->target_id, $log->meta['note']]);

        // Shown by the name on the post, cards, profile (both languages), never on the anonymous post.
        $this->get("/p/{$named}")->assertSee('pro-badge', false)->assertSee('ডাক্তার');
        $this->get('/feed')->assertSee('pro-badge', false);
        $this->get('/u/drrumana1')->assertSee('পেশার ব্যাজ আমার বাংলাদেশ যাচাই করে দেয়।');
        $this->get('/en/u/drrumana1')->assertSee('Doctor')->assertDontSee('BMDC');
        $this->assertStringNotContainsString('pro-badge', $this->postHtml($anon));
        $this->assertStringNotContainsString('ডা. রুমানা', $this->postHtml($anon));
        $this->getJson('/api/feed?limit=10')->assertOk();
        $this->assertStringNotContainsString('BMDC', json_encode(Post::find($named)->member->toArray()));

        $this->actingAs($admin)->get('/admin/badges')->assertOk()->assertSee('BMDC A-12345 checked online')->assertSee($admin->name);

        // Changing replaces it; removing takes it off everywhere.
        $this->actingAs($admin)->post('/admin/badges', ['member' => 'drrumana1', 'badge_bn' => 'শিশু বিশেষজ্ঞ', 'badge_note' => 'BMDC again'])->assertRedirect();
        $this->assertSame('শিশু বিশেষজ্ঞ', $doctor->refresh()->badge_bn);
        $this->assertNull($doctor->badge_en);
        $this->actingAs($admin)->delete("/admin/badges/{$doctor->id}")->assertRedirect('/admin/badges');
        $this->assertNull($doctor->refresh()->badge_bn);
        $this->assertNull($doctor->badge_note);
        $this->get("/p/{$named}")->assertDontSee('pro-badge', false);
        $this->assertSame(['badge_give', 'badge_edit', 'badge_remove'], AdminAction::orderBy('id')->pluck('action')->all());
        $this->actingAs($admin)->get('/admin/community/log')->assertSee('Badge removed')->assertSee('how it was checked: BMDC again');
    }

    public function test_only_admins_can_give_badges_and_deleting_the_account_drops_it(): void
    {
        $member = Accounts::signIn('email', 'm@example.test', 'মিতা');
        $this->post('/admin/badges', ['member' => $member->code, 'badge_bn' => 'ডাক্তার', 'badge_note' => 'x'])->assertRedirect('/admin/login');
        $this->assertNull($member->refresh()->badge_bn);

        // Members can't set it through their own profile update either.
        $token = $member->issueToken();
        $this->withHeader('X-Member-Token', $token)->patchJson('/api/members/me', ['name' => 'মিতা', 'badge_bn' => 'ডাক্তার']);
        $this->assertNull($member->refresh()->badge_bn);

        $member->forceFill(['badge_bn' => 'ডাক্তার', 'badge_note' => 'checked'])->save();
        Accounts::delete($member, false);
        $this->assertNull($member->refresh()->badge_bn);
        $this->assertNull($member->badge_note);
    }
}
