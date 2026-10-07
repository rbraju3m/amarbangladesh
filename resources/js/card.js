import boarding from './cards/boarding';
import { FONT, H, loadImage, W } from './cards/common';
import minimal from './cards/minimal';
import passport from './cards/passport';
import poster from './cards/poster';

/**
 * Draws the 1080×1920 story card in the browser, in one of several templates.
 * Done client-side on purpose: the browser shapes Bangla conjuncts correctly, PHP GD does not.
 */
export const TEMPLATES = [
    { key: 'passport', label: 'পাসপোর্ট', icon: '🛂', draw: passport },
    { key: 'poster', label: 'পোস্টার', icon: '🖼️', draw: poster },
    { key: 'boarding', label: 'বোর্ডিং পাস', icon: '🎫', draw: boarding },
    { key: 'minimal', label: 'মিনিমাল', icon: '✨', draw: minimal },
];

export const templateKeys = TEMPLATES.map((t) => t.key);

export async function renderCard(result, { template = 'passport', host = location.host } = {}) {
    await Promise.all([document.fonts.load(`700 100px ${FONT}`), document.fonts.load(`500 40px ${FONT}`)]).catch(() => {});

    const { draw } = TEMPLATES.find((t) => t.key === template) || TEMPLATES[0];
    const canvas = Object.assign(document.createElement('canvas'), { width: W, height: H });
    const ctx = canvas.getContext('2d');
    draw(ctx, result, { img: await loadImage(result.location.illustration), host });

    return canvas;
}

export const canvasToBlob = (canvas) => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
