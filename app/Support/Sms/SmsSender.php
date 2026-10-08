<?php

namespace App\Support\Sms;

interface SmsSender
{
    /** @param string $phone E.164, e.g. +8801712345678 */
    public function send(string $phone, string $message): void;
}
