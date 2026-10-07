import boarding, { square as boardingSquare } from './cards/boarding';
import { FONT, H, loadImage, S, W } from './cards/common';
import minimal, { square as minimalSquare } from './cards/minimal';
import passport, { square as passportSquare } from './cards/passport';
import poster, { square as posterSquare } from './cards/poster';
import { resolveTheme } from './cards/themes';

export { THEMES, themeKeys } from './cards/themes';

/**
 * Draws the share card in the browser: a template × colour theme × format (1080×1920 story or
 * 1080×1080 square for feeds; each template has a layout for both).
 * Done client-side on purpose: the browser shapes Bangla conjuncts correctly, PHP GD does not.
 */
export const TEMPLATES = [
    { key: 'passport', label: 'পাসপোর্ট', icon: '🛂', draw: passport, square: passportSquare },
    { key: 'poster', label: 'পোস্টার', icon: '🖼️', draw: poster, square: posterSquare },
    { key: 'boarding', label: 'বোর্ডিং পাস', icon: '🎫', draw: boarding, square: boardingSquare },
    { key: 'minimal', label: 'মিনিমাল', icon: '✨', draw: minimal, square: minimalSquare },
];

export const FORMATS = [
    { key: 'story', label: 'স্টোরি', w: W, h: H },
    { key: 'square', label: 'স্কয়ার', w: S, h: S },
];

export const formatKeys = FORMATS.map((f) => f.key);

export const templateKeys = TEMPLATES.map((t) => t.key);

const images = new Map();
const image = (src) => {
    if (!images.has(src)) images.set(src, loadImage(src));
    return images.get(src);
};

/** `scale` < 1 draws a smaller copy (the picker previews) with the same layout. */
export async function renderCard(result, { template = 'passport', theme = 'place', format = 'story', host = location.host, scale = 1 } = {}) {
    await Promise.all([document.fonts.load(`700 100px ${FONT}`), document.fonts.load(`500 40px ${FONT}`)]).catch(() => {});

    const tpl = TEMPLATES.find((t) => t.key === template) || TEMPLATES[0];
    const size = FORMATS.find((f) => f.key === format) || FORMATS[0];
    const draw = size.key === 'square' ? tpl.square : tpl.draw;
    const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(size.w * scale), height: Math.round(size.h * scale) });
    const ctx = canvas.getContext('2d');
    ctx.scale(scale, scale);
    draw(ctx, result, { img: await image(result.location.illustration), host, t: resolveTheme(theme, result.location) });

    return canvas;
}

export const canvasToBlob = (canvas) => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
