<?php

namespace Tests\Feature;

use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    private function trustedProxiesFor(string $setting): mixed
    {
        $_SERVER['TRUSTED_PROXIES'] = $setting;
        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            unset($_SERVER['TRUSTED_PROXIES']);
        }
    }

    public function test_trusted_proxies_setting_is_parsed(): void
    {
        $this->assertNull($this->trustedProxiesFor(''));
        $this->assertSame('*', $this->trustedProxiesFor('*'));
        $this->assertContains('173.245.48.0/20', $this->trustedProxiesFor('cloudflare'));
        $this->assertContains('2606:4700::/32', $this->trustedProxiesFor('cloudflare'));
        $this->assertContains('10.0.0.5', $this->trustedProxiesFor('cloudflare, 10.0.0.5'));
    }

    public function test_visitor_ip_comes_from_cloudflare_but_not_from_anyone_else(): void
    {
        config(['trustedproxy.proxies' => $this->trustedProxiesFor('cloudflare')]);
        Route::get('/_ip', fn () => request()->ip());

        // Via a Cloudflare edge: the client IP it appended is used, a spoofed one before it is not.
        $this->withServerVariables(['REMOTE_ADDR' => '162.158.1.1'])
            ->get('/_ip', ['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'])
            ->assertSeeText('203.0.113.9');

        // Directly from the internet: forwarded headers are ignored.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->get('/_ip', ['X-Forwarded-For' => '203.0.113.9'])
            ->assertSeeText('198.51.100.7');
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unknown_result_link_shows_a_friendly_bangla_page(): void
    {
        $this->get('/r/doesnotexist')->assertNotFound()
            ->assertSee('এই লিংকে কিছু নেই', false)
            ->assertSee('href="/quiz"', false);

        $this->getJson('/api/nothing')->assertNotFound()->assertJsonStructure(['message']);
    }
}
