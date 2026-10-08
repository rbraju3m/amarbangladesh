<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Site languages. Bangla lives at /…, English at /en/… : each language is its own URL, so pages
 * stay cacheable (a cookie-based switch would not be). Interface text uses Laravel's JSON
 * translations with the Bangla text as the key, so Bangla needs no translation file.
 */
final class Lang
{
    public const LOCALES = ['bn', 'en'];

    public static function current(): string
    {
        return app()->getLocale() === 'en' ? 'en' : 'bn';
    }

    public static function isEnglish(): bool
    {
        return self::current() === 'en';
    }

    /** A named public route in the current language ("feed" → /feed or /en/feed). */
    public static function route(string $name, mixed $parameters = [], bool $absolute = false): string
    {
        return route(self::isEnglish() ? "en.{$name}" : $name, $parameters, $absolute);
    }

    /** "/feed" → "/en/feed" in English. */
    public static function path(string $path): string
    {
        return self::isEnglish() ? '/en'.($path === '/' ? '' : $path) : $path;
    }

    /** This page in another language (for the switch and hreflang). */
    public static function switchUrl(string $to): string
    {
        $path = '/'.ltrim(preg_replace('#^/?en(?=/|$)#', '', request()->getPathInfo()), '/');
        $path = $to === 'en' ? '/en'.($path === '/' ? '' : $path) : $path;
        $query = request()->getQueryString();

        return url($path).($query ? '?'.$query : '');
    }

    /**
     * A counted phrase: Lang::choice(':nটি উত্তর', 3) → "৩টি উত্তর" / "3 answers".
     * The English value in lang/en.json is "singular|plural"; :n is the number in the page's digits.
     */
    public static function choice(string $key, int $count, array $replace = []): string
    {
        $replace = ['n' => self::num($count)] + $replace;

        // Bangla has no plural forms; trans_choice() would also fall back to English for a key
        // missing from a Bangla file, so it is only used for English.
        return self::isEnglish() ? trans_choice($key, $count, $replace) : __($key, $replace);
    }

    /** "রিয়া" → "রিয়ার", "Rashed" → "Rashed's" / "Rashed-এর", by page language. */
    public static function possessive(string $name): string
    {
        $name = trim($name);

        return self::isEnglish() ? $name.(str_ends_with(strtolower($name), 's') ? "'" : "'s") : Bangla::possessive($name);
    }

    public static function num(int|string $value): string
    {
        return self::isEnglish() ? (string) $value : Bangla::digits($value);
    }

    public static function ago(CarbonInterface $time): string
    {
        if (! self::isEnglish()) {
            return Bangla::ago($time);
        }
        $seconds = max(0, now()->getTimestamp() - $time->getTimestamp());

        return match (true) {
            $seconds < 60 => 'just now',
            $seconds < 7 * 86400 => $time->locale('en')->diffForHumans(['short' => false, 'parts' => 1]),
            default => self::date($time),
        };
    }

    public static function date(CarbonInterface $time): string
    {
        if (! self::isEnglish()) {
            return Bangla::date($time);
        }
        $time = $time->copy()->setTimezone(config('app.timezone'))->locale('en');

        return $time->year === now()->year ? $time->format('j M') : $time->format('j M Y');
    }
}
