import boarding from './cards/boarding';
import { FONT, H, loadImage, W } from './cards/common';
import minimal from './cards/minimal';
import passport from './cards/passport';
import poster from './cards/poster';
import { resolveTheme } from './cards/themes';

export { THEMES, themeKeys } from './cards/themes';

/**
 * Draws the 1080×1920 story card in the browser, in one of several templates × colour themes.
 * Done client-side on purpose: the browser shapes Bangla conjuncts correctly, PHP GD does not.
 */
export const TEMPLATES = [
    { key: 'passport', label: 'পাসপোর্ট', icon: '🛂', draw: passport },
    { key: 'poster', label: 'পোস্টার', icon: '🖼️', draw: poster },
    { key: 'boarding', label: 'বোর্ডিং পাস', icon: '🎫', draw: boarding },
    { key: 'minimal', label: 'মিনিমাল', icon: '✨', draw: minimal },
];

export const templateKeys = TEMPLATES.map((t) => t.key);

const images = new Map();
const image = (src) => {
    if (!images.has(src)) images.set(src, loadImage(src));
    return images.get(src);
};

/** `scale` < 1 draws a smaller copy (the picker previews) with the same layout. */
export async function renderCard(result, { template = 'passport', theme = 'place', host = location.host, scale = 1 } = {}) {
    await Promise.all([document.fonts.load(`700 100px ${FONT}`), document.fonts.load(`500 40px ${FONT}`)]).catch(() => {});

    const { draw } = TEMPLATES.find((t) => t.key === template) || TEMPLATES[0];
    const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(W * scale), height: Math.round(H * scale) });
    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    draw(ctx, result, { img: await image(result.location.illustration), host, t: resolveTheme(theme, result.location) });

    return canvas;
}

export const canvasToBlob = (canvas) => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
