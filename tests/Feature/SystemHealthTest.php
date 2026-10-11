<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Heartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_see_versions_sizes_and_no_secrets(): void
    {
        $this->get('/admin/system')->assertRedirect(); // admins only
        config(['mail.mailers.smtp.password' => 'very-secret-mail-pass', 'database.connections.mysql.password' => 'very-secret-db-pass']);

        $html = $this->actingAs(User::factory()->create())->get('/admin/system')->assertOk()
            ->assertSee('System')->assertSee(PHP_VERSION)->assertSee(app()->version())->assertSee('Biggest tables')
            ->getContent();
        foreach (['very-secret', (string) config('app.key'), (string) config('admin.super_admin.email')] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    public function test_the_scheduler_and_commands_leave_heartbeats_and_late_ones_are_flagged(): void
    {
        Cache::flush();
        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admin/system')->assertSee('Scheduler running')->assertSee('No run recorded yet')->assertSee('never');

        Artisan::call('schedule:run'); // the every-minute heartbeat (and the 5-minute email run when due)
        $this->assertNotNull(Heartbeat::get('scheduler'));
        Artisan::call('community:send-notification-emails');
        Artisan::call('community:prune-photos');
        $this->assertSame(['sent' => 0, 'failed' => 0], array_diff_key(Heartbeat::get('community:send-notification-emails'), ['at' => 1]));
        $this->assertSame(0, Heartbeat::get('community:prune-photos')['deleted']);

        $this->actingAs($admin)->get('/admin/system')->assertDontSee('No run recorded yet')->assertSee('sent 0');

        // Ten minutes without a scheduler run: flagged.
        $this->travel(10)->minutes();
        $this->actingAs($admin)->get('/admin/system')->assertSee('10 minutes ago');
        $this->assertFalse($this->jobs($admin)['scheduler']['ok']);
        $this->assertTrue($this->jobs($admin)['community:prune-photos']['ok']);
    }

    public function test_failed_emails_are_counted_for_24_hours_without_their_message(): void
    {
        $admin = User::factory()->create();
        Heartbeat::mailFailed(new RuntimeException('could not reach rashed@example.test'));

        $this->actingAs($admin)->get('/admin/system')->assertSee('No email failures in 24 h')->assertSee('1 notification email(s) failed')
            ->assertSee('RuntimeException')->assertDontSee('rashed@example.test');

        $this->travel(25)->hours();
        $this->actingAs($admin)->get('/admin/system')->assertDontSee('notification email(s) failed');
    }

    private function jobs(User $admin): array
    {
        return $this->actingAs($admin)->get('/admin/system')->viewData('jobs');
    }
}
