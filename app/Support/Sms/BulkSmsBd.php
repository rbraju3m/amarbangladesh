<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** bulksmsbd.net HTTP API. Response code 202 means accepted. */
class BulkSmsBd implements SmsSender
{
    public function __construct(private string $apiKey, private string $senderId) {}

    public function send(string $phone, string $message): void
    {
        $response = Http::timeout(10)->asForm()->post('https://bulksmsbd.net/api/smsapi', [
            'api_key' => $this->apiKey,
            'senderid' => $this->senderId,
            'number' => ltrim($phone, '+'),
            'message' => $message,
        ]);

        if (! $response->successful() || (int) $response->json('response_code') !== 202) {
            throw new RuntimeException('SMS not sent: '.$response->body());
        }
    }
}
