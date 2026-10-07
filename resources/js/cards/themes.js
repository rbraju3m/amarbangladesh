import { INK, INK_2, PAPER } from './common';

/**
 * Colour themes for the story cards; every template can be drawn in any of them.
 * `ink`/`ink2`/`accent` are for text and shapes on the background; `cardInk`/`cardInk2`/`cardAccent`
 * for the white panels (passport page, ticket), so dark themes stay readable. All colours are
 * 6-digit hex because templates append alpha (`accent + '33'`).
 * "place" is the original look, coloured by the result's location.
 */
export const THEMES = [
    { key: 'place', label: 'জায়গার রং' },
    { key: 'cream', label: 'ক্রিম', bg: ['#f6f2ea', '#e9e0cd'], ink: '#17201c', ink2: '#6b716d', accent: '#0f6b4f' },
    { key: 'sunset', label: 'গোধূলি', bg: ['#fff5ee', '#ffdcc7'], ink: '#3b1f23', ink2: '#8f5c55', accent: '#e63971' },
    { key: 'ocean', label: 'সাগর', bg: ['#eef5fb', '#d3e6f7'], ink: '#0f1d33', ink2: '#4b6584', accent: '#2356e8' },
    { key: 'night', label: 'রাত', dark: true, bg: ['#191835', '#2c2a63'], ink: '#ffffff', ink2: '#c9c8f5', accent: '#9d9bff', cardAccent: '#5b59e6' },
    { key: 'emerald', label: 'পান্না', dark: true, bg: ['#0c5a42', '#1b8a66'], ink: '#ffffff', ink2: '#d8efe6', accent: '#ffd166', cardAccent: '#0f6b4f' },
];

export const themeKeys = THEMES.map((t) => t.key);

/** The full palette for a theme key, filling in the place colour and panel inks. */
export function resolveTheme(key, location) {
    const t = THEMES.find((x) => x.key === key) || THEMES[0];
    if (t.key === 'place') {
        return { key: 'place', dark: false, bg: [PAPER, PAPER], ink: INK, ink2: INK_2, accent: location.accent, cardInk: INK, cardInk2: INK_2, cardAccent: location.accent };
    }
    return {
        ...t,
        cardInk: t.dark ? INK : t.ink,
        cardInk2: t.dark ? INK_2 : t.ink2,
        cardAccent: t.cardAccent || t.accent,
    };
}

/** Fills the canvas with the theme's vertical background gradient. */
export function fillBackground(ctx, t, w, h) {
    const g = ctx.createLinearGradient(0, 0, 0, h);
    g.addColorStop(0, t.bg[0]);
    g.addColorStop(1, t.bg[1]);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, w, h);
}
