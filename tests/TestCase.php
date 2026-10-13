<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Email sign-up as the browser does it: the form, then the 6-digit code from the email (the array
     * mailer keeps sent mail). Returns the verify response (201 with `member` and `token`).
     */
    protected function signUpByEmail(string $name, string $email, string $password = 'secret-pass', array $headers = []): TestResponse
    {
        $this->withHeaders($headers)->postJson('/api/auth/email/register', ['name' => $name, 'email' => $email, 'password' => $password])->assertNoContent();

        return $this->withHeaders($headers)->postJson('/api/auth/email/verify', ['email' => $email, 'code' => $this->lastEmailCode()]);
    }

    /** The 6-digit code in the last email sent. */
    protected function lastEmailCode(): string
    {
        $messages = app('mailer')->getSymfonyTransport()->messages();
        preg_match('/\b\d{6}\b/', $messages[count($messages) - 1]->getOriginalMessage()->getTextBody(), $code);

        return $code[0] ?? '';
    }
}
