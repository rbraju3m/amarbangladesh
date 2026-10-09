<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\Post;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

/**
 * Formatted posts and answers (bold, italic, lists, links; posts also a small heading).
 *
 * The editor sends HTML with `format=html`; it is cleaned here with HTML Purifier and stored in
 * `body_html`, and a plain-text copy goes in `body` for excerpts, search, emails and the duplicate
 * check. Without the editor (no JS, replies, old rows) there is only `body`, rendered by Text::render().
 */
final class RichText
{
    /** The cleaned HTML may not be longer than this (the 5000-character limit applies to the text). */
    public const MAX_HTML = 20000;

    private const TAGS = 'p,br,strong,em,ul,ol,li,a[href]';

    /** @var array<string, HTMLPurifier> */
    private static array $purifiers = [];

    /**
     * What the member wrote, from a request: [plain text, cleaned HTML or null].
     * HTML only when the editor sent it (`format=html`) and `$rich` allows it (replies stay plain).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function input(Request $request, bool $rich = true, bool $headings = false): array
    {
        if (! $rich || $request->input('format') !== 'html') {
            return [Text::clean($request->input('body')), null];
        }
        $html = self::clean($request->input('body'), $headings);

        return [self::toText($html), $html];
    }

    /** Only the allowed tags survive; links get nofollow and a new tab; bare http(s) URLs become links. */
    public static function clean(?string $html, bool $headings = false): ?string
    {
        $html = self::purifier($headings)->purify((string) $html);
        $html = preg_replace('~<a>(.*?)</a>~su', '$1', $html); // a link whose address was refused
        $html = preg_replace('~<p>(?:\s|&nbsp;|<br />)*</p>~u', '', $html);
        $html = trim($html);

        return self::toText($html) === null ? null : $html;
    }

    /** Plain text of cleaned HTML: paragraphs, line breaks and list items become lines. */
    public static function toText(?string $html): ?string
    {
        $text = preg_replace(['~<br\s*/?>~i', '~</(p|h3|ul|ol)>~i', '~<li>~i', '~</li>~i'], ["\n", "\n\n", '• ', "\n"], (string) $html);

        return Text::clean(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Links in cleaned HTML: the <a> tags, plus addresses left as text (www.… isn't linked by the cleaner). */
    public static function linkCount(?string $html): int
    {
        $links = preg_match_all('~<a\s~i', (string) $html);
        $rest = preg_replace('~<a\s.*?</a>~isu', ' ', (string) $html);

        return $links + Text::linkCount(strip_tags($rest));
    }

    /** The body as HTML: the stored formatted version, or the plain text made safe. */
    public static function render(Post|Answer $item): HtmlString
    {
        return $item->body_html !== null ? new HtmlString($item->body_html) : Text::render($item->body);
    }

    private static function purifier(bool $headings): HTMLPurifier
    {
        return self::$purifiers[$headings ? 'post' : 'answer'] ??= (function () use ($headings) {
            $config = HTMLPurifier_Config::createDefault();
            $cache = storage_path('framework/purifier');
            File::ensureDirectoryExists($cache);
            $config->set('Cache.SerializerPath', $cache);
            $config->set('HTML.Allowed', self::TAGS.($headings ? ',h3' : ''));
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
            $config->set('HTML.Nofollow', true);
            $config->set('HTML.TargetBlank', true);
            $config->set('AutoFormat.Linkify', true);
            $config->set('AutoFormat.RemoveEmpty', true);

            return new HTMLPurifier($config);
        })();
    }
}
