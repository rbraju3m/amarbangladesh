<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/** Local development: the message (and so the sign-in code) goes to the log instead of a phone. */
class LogSms implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
