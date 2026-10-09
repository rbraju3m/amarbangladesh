<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Moderation;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Post;
use App\Support\Sms\SmsSender;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    /** Captures SMS instead of sending. */
    private function fakeSms(): object
    {
        $sms = new class implements SmsSender
        {
            public array $sent = [];

            public function send(string $phone, string $message): void
            {
                $this->sent[] = [$phone, $message];
            }
        };
        $this->app->instance(SmsSender::class, $sms);
        config(['services.sms.driver' => 'log']);

        return $sms;
    }

    public function test_email_register_login_and_logout(): void
    {
        $token = $this->postJson('/api/auth/email/register', ['name' => 'মিতু', 'email' => 'Mitu@Example.com', 'password' => 'secret-pass'])
            ->assertCreated()->assertJson(['member' => ['name' => 'মিতু', 'account' => true]])->json('token');

        $this->postJson('/api/auth/email/register', ['name' => 'আরেকজন', 'email' => 'mitu@example.com', 'password' => 'secret-pass'])->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/email/login', ['email' => 'mitu@example.com', 'password' => 'wrong-pass'])->assertJsonValidationErrors('email');
        $second = $this->postJson('/api/auth/email/login', ['email' => 'MITU@example.com', 'password' => 'secret-pass'])->assertOk()->json('token');

        $this->withHeader('X-Member-Token', $token)->postJson('/api/posts', ['title' => 'ইমেইলে লগইন করে প্রশ্ন করা যায়?'])->assertCreated();
        $this->withHeader('X-Member-Token', $token)->postJson('/api/auth/logout')->assertNoContent();
        $this->withHeader('X-Member-Token', $token)->getJson('/api/members/me')->assertUnauthorized();
        $this->withHeader('X-Member-Token', $second)->getJson('/api/members/me')->assertOk(); // other device stays signed in
    }

    public function test_password_reset_by_email_signs_out_other_devices(): void
    {
        $old = $this->postJson('/api/auth/email/register', ['name' => 'মিতু', 'email' => 'mitu@example.com', 'password' => 'secret-pass'])->json('token');

        $this->postJson('/api/auth/email/forgot', ['email' => 'nobody@example.com'])->assertNoContent();
        $this->postJson('/api/auth/email/forgot', ['email' => 'mitu@example.com'])->assertNoContent();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        preg_match('~/reset-password#t=([A-Za-z0-9]+)~', $messages[0]->getOriginalMessage()->getTextBody(), $m);

        $this->postJson('/api/auth/email/reset', ['token' => 'wrong', 'password' => 'new-secret-1'])->assertJsonValidationErrors('token');
        $this->postJson('/api/auth/email/reset', ['token' => $m[1], 'password' => 'new-secret-1'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/auth/email/reset', ['token' => $m[1], 'password' => 'again-secret'])->assertJsonValidationErrors('token'); // single use

        $this->withHeader('X-Member-Token', $old)->getJson('/api/members/me')->assertUnauthorized();
        $this->postJson('/api/auth/email/login', ['email' => 'mitu@example.com', 'password' => 'new-secret-1'])->assertOk();
    }

    public function test_phone_code_sign_in(): void
    {
        $sms = $this->fakeSms();

        $this->postJson('/api/auth/phone/send', ['phone' => '12345'])->assertJsonValidationErrors('phone');
        $this->postJson('/api/auth/phone/send', ['phone' => '০১৭১২-৩৪৫৬৭৮'])->assertNoContent();
        $this->postJson('/api/auth/phone/send', ['phone' => '+8801712345678'])->assertStatus(429); // resend wait

        [$phone, $message] = $sms->sent[0];
        $this->assertSame('+8801712345678', $phone);
        preg_match('/\d{6}/', $message, $code);

        $this->postJson('/api/auth/phone/verify', ['phone' => '01712345678', 'code' => '000000'])->assertJsonValidationErrors('code');
        $token = $this->postJson('/api/auth/phone/verify', ['phone' => '01712345678', 'code' => $code[0], 'name' => 'রাশেদ'])
            ->assertOk()->assertJson(['member' => ['name' => 'রাশেদ', 'account' => true]])->json('token');
        $this->postJson('/api/auth/phone/verify', ['phone' => '01712345678', 'code' => $code[0]])->assertJsonValidationErrors('code'); // used up

        $this->assertSame(1, Member::count());
        $this->withHeader('X-Member-Token', $token)->postJson('/api/posts', ['title' => 'ফোনে লগইন করে প্রশ্ন করা যায়?'])->assertCreated();
    }

    public function test_a_phone_code_dies_after_too_many_wrong_tries(): void
    {
        $sms = $this->fakeSms();
        $this->postJson('/api/auth/phone/send', ['phone' => '01812345678'])->assertNoContent();
        preg_match('/\d{6}/', $sms->sent[0][1], $code);

        foreach (range(1, Accounts::CODE_TRIES) as $i) {
            $this->postJson('/api/auth/phone/verify', ['phone' => '01812345678', 'code' => '111111'])->assertJsonValidationErrors('code');
        }
        $this->postJson('/api/auth/phone/verify', ['phone' => '01812345678', 'code' => $code[0]])->assertJsonValidationErrors('code');
    }

    public function test_google_sign_in_hands_the_token_over_in_the_fragment(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        Socialite::fake('google', (new SocialUser)->map(['id' => 'g-123', 'name' => 'Nadia Rahman', 'email' => 'nadia@example.com']));

        $this->get('/auth/google/redirect')->assertRedirect();
        $location = $this->get('/auth/google/callback')->assertRedirect()->headers->get('Location');

        $this->assertMatchesRegularExpression('~/auth/done#t=[A-Za-z0-9]{40}$~', $location);
        $this->assertSame('Nadia Rahman', Accounts::find('google', 'g-123')->name);
        $this->assertSame('nadia@example.com', Accounts::find('google', 'g-123')->email, 'kept for answer notifications');
        $this->get('/auth/done')->assertOk();

        $this->get('/auth/facebook/redirect')->assertNotFound(); // not configured
    }

    public function test_signing_in_brings_a_device_only_members_posts_along(): void
    {
        $legacy = Member::create(['code' => 'legacy01', 'name' => 'পুরনো']);
        $legacyToken = $legacy->issueToken();
        $own = Post::create(['member_id' => $legacy->id, 'title' => 'লগইনের আগের যুগের একটা প্রশ্ন']);
        $other = Post::create(['member_id' => Accounts::signIn('email', 'other@example.com', 'অন্য')->id, 'title' => 'অন্য কারও একটা প্রশ্ন এখানে']);

        $token = $this->postJson('/api/auth/email/register', ['name' => 'মিতু', 'email' => 'mitu@example.com', 'password' => 'secret-pass'])->json('token');
        $account = Accounts::find('email', 'mitu@example.com');
        // Both identities marked the same post helpful: after merging it counts once.
        Moderation::toggleHelpful($legacy, $other);
        Moderation::toggleHelpful($account, $other);
        $this->assertSame(2, $other->fresh()->helpful_count);

        $this->withHeader('X-Member-Token', $token)->postJson('/api/auth/link', ['legacy_token' => $legacyToken])->assertOk();

        $this->assertSame($account->id, $own->fresh()->member_id);
        $this->assertSame(1, $other->fresh()->helpful_count);
        $this->assertSame(1, HelpfulMark::count());
        $this->assertNull(Member::find($legacy->id));
        $this->withHeader('X-Member-Token', $legacyToken)->getJson('/api/members/me')->assertUnauthorized();
    }

    public function test_sign_in_methods_without_keys_are_hidden(): void
    {
        config(['services.google.client_id' => null, 'services.facebook.client_id' => null, 'services.sms.driver' => null]);
        $this->assertSame(['email'], Accounts::providers());
        $this->postJson('/api/auth/phone/send', ['phone' => '01712345678'])->assertNotFound();
    }
}
