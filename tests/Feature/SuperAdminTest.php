<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\QuizResult;
use App\Models\User;
use App\Quiz\QuizConfig;
use App\Support\SuperAdmin\SuperAdminIsProtected;
use App\Support\SuperAdmin\SuperAdminPasswordMissing;
use App\Support\SuperAdmin\SuperAdminProvisioner;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function superAdmin(): User
    {
        return User::where('email', 'super@example.test')->firstOrFail();
    }

    public function test_migrating_creates_the_super_admin_who_can_log_in(): void
    {
        $user = $this->superAdmin();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check('test-password-123', $user->password));

        $this->post('/admin/login', ['email' => 'super@example.test', 'password' => 'test-password-123'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk()->assertSee('Super admin');
    }

    public function test_provisioning_is_idempotent_and_repairs_a_removed_flag(): void
    {
        (new SuperAdminProvisioner)->ensure();
        $this->assertSame(1, User::where('email', 'super@example.test')->count());

        // Removed behind the model's back (raw SQL), then put back without needing a password.
        User::whereKey($this->superAdmin()->id)->toBase()->update(['is_super_admin' => false]);
        config(['admin.super_admin.password' => null]);
        (new SuperAdminProvisioner)->ensure();
        $this->assertTrue($this->superAdmin()->is_super_admin);
    }

    public function test_a_changed_password_is_kept_unless_reset_is_asked_for(): void
    {
        $user = $this->superAdmin();
        $user->update(['password' => 'my-new-password-9']);

        (new SuperAdminProvisioner)->ensure();
        $this->assertTrue(Hash::check('my-new-password-9', $this->superAdmin()->password));

        $this->artisan('admin:ensure-super-admin', ['--reset-password' => true])->assertSuccessful();
        $this->assertTrue(Hash::check('test-password-123', $this->superAdmin()->password));
    }

    public function test_the_super_admin_cannot_be_deleted_demoted_or_readdressed(): void
    {
        $user = $this->superAdmin();

        foreach ([
            fn () => $user->delete(),
            fn () => $user->forceFill(['is_super_admin' => false])->save(),
            fn () => $user->forceFill(['email' => 'other@example.test'])->save(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('expected the super admin to be protected');
            } catch (SuperAdminIsProtected) {
                $user->refresh();
            }
        }
        $this->assertTrue($this->superAdmin()->is_super_admin);

        // Ordinary admins are unaffected, and the flag can't be mass-assigned.
        // (factories bypass $fillable, so use create() like real code would).
        $other = User::create(['name' => 'Ed', 'email' => 'ed@example.test', 'password' => 'x', 'is_super_admin' => true]);
        $this->assertFalse($other->fresh()->is_super_admin);
        $other->delete();
        $this->assertModelMissing($other);
    }

    public function test_creating_without_a_password_or_with_a_weak_one_is_refused(): void
    {
        config(['admin.super_admin.email' => 'new@example.test', 'admin.super_admin.password' => '']);
        $this->artisan('admin:ensure-super-admin')->assertFailed();

        config(['admin.super_admin.password' => 'short1']);
        $this->expectException(SuperAdminPasswordMissing::class);
        (new SuperAdminProvisioner)->ensure();
    }

    public function test_purging_demo_plays_keeps_content_and_admins(): void
    {
        $answers = array_map(fn ($o) => array_key_first($o), array_values(QuizConfig::fromDatabase()->questions));
        $code = $this->postJson('/api/results', ['answers' => $answers])->assertCreated()->json('result.code');
        $this->postJson('/api/results', ['answers' => $answers, 'ref' => $code])->assertCreated();
        $this->assertSame(2, QuizResult::count());
        $this->assertGreaterThan(0, AnalyticsEvent::count());
        $questions = QuizConfig::fromDatabase()->questions;

        $this->artisan('quiz:purge-plays')->expectsConfirmation('Delete them?', 'no')->assertFailed();
        $this->assertSame(2, QuizResult::count());

        $this->artisan('quiz:purge-plays', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, QuizResult::count());
        $this->assertSame(0, AnalyticsEvent::count());
        $this->assertEquals($questions, QuizConfig::fromDatabase()->questions);
        $this->assertTrue($this->superAdmin()->exists);
        $this->get('/r/'.$code)->assertNotFound();
    }
}
