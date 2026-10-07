<?php

namespace App\Support;

final class Device
{
    /** Coarse device class only — the full user agent is never stored. */
    public static function fromUserAgent(?string $ua): string
    {
        $ua = (string) $ua;

        return match (true) {
            (bool) preg_match('/bot|crawl|spider|facebookexternalhit|WhatsApp|preview/i', $ua) => 'bot',
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|Android|iPhone/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }
}
