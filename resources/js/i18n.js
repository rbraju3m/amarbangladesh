import { bnDigits, possessive as bnPossessive } from './bn';

/**
 * Interface language for scripts, mirroring App\Support\Lang: the page's <html lang> decides.
 * Bangla text is the key; English pages load lang/en.json (a separate chunk, only for English).
 */
export const lang = document.documentElement.lang === 'en' ? 'en' : 'bn';

let dict = {};

export async function loadDictionary() {
    if (lang !== 'en') return;
    try {
        dict = (await import('../../lang/en.json')).default;
    } catch {}
}

/**
 * t('আবার চেষ্টা করুন') → 'Try again' on English pages. ":name" placeholders are filled from params.
 * Counted phrases ("singular|plural" in en.json) pick by params.count, like Lang::choice in PHP.
 */
export function t(text, params = {}) {
    let out = dict[text] ?? text;
    if (out.includes('|') && params.count !== undefined) {
        const parts = out.split('|');
        out = parts[Number(params.count) === 1 ? 0 : 1] ?? parts[0];
    }
    for (const [key, value] of Object.entries(params)) out = out.replaceAll(`:${key}`, value);
    return out;
}

/** "/feed" → "/en/feed" on English pages. */
export const path = (p) => (lang === 'en' ? `/en${p === '/' ? '' : p}` : p);

/** Digits in the page's language. */
export const num = (value) => (lang === 'en' ? String(value) : bnDigits(value));

/** Header and query additions so the server answers in the page's language. */
export const localeHeaders = () => (lang === 'en' ? { 'X-Locale': 'en' } : {});
export const withLang = (url) => (lang === 'en' ? `${url}${url.includes('?') ? '&' : '?'}lang=en` : url);

/** "রিয়া" → "রিয়ার" on Bangla pages, "Rashed" → "Rashed's" on English ones (mirrors Lang::possessive). */
export function possessive(name) {
    name = (name || '').trim();
    if (lang !== 'en') return bnPossessive(name);
    return /s$/i.test(name) ? `${name}'` : `${name}'s`;
}

/** "আমার বাংলাদেশ" as "my Bangladesh" (the same words are also the site's name, which stays "Amar Bangladesh"). */
export const myBangladesh = () => (lang === 'en' ? 'My Bangladesh' : 'আমার বাংলাদেশ');
