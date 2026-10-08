<?php

namespace App\Community;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Cleaning what members write, and turning it back into safe HTML. */
final class Text
{
    /** More links than this in one post or answer is almost always spam. */
    public const MAX_LINKS = 2;

    private const URL = '~\b(?:https?://|www\.)[^\s<]+~iu';

    /** Plain text: no tags, tidy whitespace, at most one blank line in a row. */
    public static function clean(?string $text): ?string
    {
        $text = strip_tags((string) $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = trim(preg_replace("/\n{3,}/u", "\n\n", $text));

        return $text === '' ? null : $text;
    }

    /** A title is one line. */
    public static function line(?string $text): ?string
    {
        $text = self::clean($text);

        return $text === null ? null : trim(preg_replace('/\s+/u', ' ', $text));
    }

    public static function linkCount(?string $text): int
    {
        return preg_match_all(self::URL, (string) $text);
    }

    /** Escaped text with line breaks kept and links made clickable (marked as user content). */
    public static function render(?string $text): HtmlString
    {
        $html = e((string) $text);
        $html = preg_replace_callback(self::URL, function ($m) {
            $label = $m[0];
            $trail = '';
            // Keep sentence punctuation out of the link.
            while ($label !== '' && str_contains('.,!?)।', mb_substr($label, -1))) {
                $trail = mb_substr($label, -1).$trail;
                $label = mb_substr($label, 0, -1);
            }
            $href = str_starts_with(strtolower($label), 'www.') ? 'https://'.$label : $label;

            return '<a href="'.$href.'" rel="nofollow ugc noopener" target="_blank" class="font-semibold text-green-text underline underline-offset-2 break-all">'.Str::limit($label, 60).'</a>'.$trail;
        }, $html);

        return new HtmlString(nl2br($html, false));
    }

    public static function excerpt(?string $text, int $length = 140): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', (string) $text), $length, '…');
    }
}
