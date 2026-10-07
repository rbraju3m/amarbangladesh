<?php

namespace App\Support;

final class Bangla
{
    private const DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

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
}
