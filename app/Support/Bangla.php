<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Bangla
{
    private const DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    private const MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    public static function digits(int|string $value): string
    {
        return strtr((string) $value, array_combine(range(0, 9), self::DIGITS));
    }

    /** "রিয়া" → "রিয়ার", "রাশেদ" → "রাশেদের", "Rashed" → "Rashed-এর". */
    public static function possessive(string $name): string
    {
        $name = trim($name);
        if (preg_match('/[A-Za-z0-9]$/u', $name)) {
            return $name.'-এর';
        }

        return preg_match('/[\x{0985}-\x{0994}\x{09BE}-\x{09CC}\x{09D7}]$/u', $name) ? $name.'র' : $name.'ের';
    }

    /** Drop tags and link-like words, then anything that is not a letter, mark, space, dot or hyphen. */
    public static function cleanName(?string $name): ?string
    {
        $name = strip_tags((string) $name);
        $name = preg_replace('~\S*(://|www\.|\.(com|net|org|xyz|io|bd|me|ly)\b)\S*~iu', '', $name);
        $name = preg_replace('/[^\p{L}\p{M}\s.\-]/u', '', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name), ' .-');

        return $name === '' ? null : mb_substr($name, 0, 20);
    }

    /** "এইমাত্র", "৫ মিনিট আগে", "৩ ঘণ্টা আগে", "২ দিন আগে", then a date: "৯ অক্টোবর" (with the year if it isn't this one). */
    public static function ago(CarbonInterface $time): string
    {
        $seconds = max(0, now()->getTimestamp() - $time->getTimestamp());

        return match (true) {
            $seconds < 60 => 'এইমাত্র',
            $seconds < 3600 => self::digits(intdiv($seconds, 60)).' মিনিট আগে',
            $seconds < 86400 => self::digits(intdiv($seconds, 3600)).' ঘণ্টা আগে',
            $seconds < 7 * 86400 => self::digits(intdiv($seconds, 86400)).' দিন আগে',
            default => self::date($time),
        };
    }

    public static function date(CarbonInterface $time): string
    {
        $time = $time->copy()->setTimezone(config('app.timezone'));
        $date = self::digits($time->day).' '.self::MONTHS[$time->month - 1];

        return $time->year === now()->year ? $date : $date.' '.self::digits($time->year);
    }
}
