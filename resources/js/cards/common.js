/** Shared canvas helpers for the 1080×1920 story cards (see ../card.js). */
export const W = 1080;
export const H = 1920;
/** Side of the square 1080×1080 feed card. */
export const S = 1080;
export const FONT = '"Anek Bangla Variable", "Noto Sans Bengali", sans-serif';
export const INK = '#14211b';
export const INK_2 = '#4b5a52';
export const PAPER = '#fbf8f1';
export const RED = '#e03a3e';
export const GREEN = '#006a4e';

export const loadImage = (src) =>
    new Promise((resolve) => {
        if (!src) return resolve(null);
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
    });

export function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}

/** Sets the largest font (down from `size`) at which `text` fits in `maxWidth`. */
export function fitText(ctx, text, maxWidth, size, weight) {
    let s = size;
    do {
        ctx.font = `${weight} ${s}px ${FONT}`;
        s -= 4;
    } while (ctx.measureText(text).width > maxWidth && s > 24);
}

export function font(ctx, weight, size) {
    ctx.font = `${weight} ${size}px ${FONT}`;
}

/** Draws a place illustration (400×300 artwork) to cover the box, on an accent fill. */
export function drawCover(ctx, img, x, y, w, h, fill) {
    ctx.fillStyle = fill;
    ctx.fillRect(x, y, w, h);
    if (!img) return;
    const scale = Math.max(w / 400, h / 300);
    ctx.drawImage(img, x + (w - 400 * scale) / 2, y + (h - 300 * scale) / 2, 400 * scale, 300 * scale);
}

/** A rubber-stamp style badge centred on (x, y). */
export function stamp(ctx, text, x, y, angle, color) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    font(ctx, 600, 40);
    const w = ctx.measureText(text).width + 56;
    ctx.strokeStyle = color;
    ctx.lineWidth = 4;
    roundRect(ctx, -w / 2, -38, w, 76, 18);
    ctx.stroke();
    ctx.setLineDash([3, 7]);
    ctx.lineWidth = 2;
    roundRect(ctx, -w / 2 + 9, -29, w - 18, 58, 12);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.fillStyle = color;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, 0, 3);
    ctx.restore();
    return w;
}

/** Highlighter-marker swipe behind centred text whose baseline is at y. */
export function highlight(ctx, text, cx, y, color) {
    const w = ctx.measureText(text).width;
    ctx.save();
    ctx.translate(cx, y);
    ctx.rotate(-0.02);
    ctx.fillStyle = color;
    roundRect(ctx, -w / 2 - 28, -46, w + 56, 60, 22);
    ctx.fill();
    ctx.restore();
}

/** "তোমার বাংলাদেশ কোথায়?" + site host, centred near the bottom edge. */
export function footer(ctx, host, { color = INK, muted = INK_2 } = {}) {
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = color;
    font(ctx, 700, 54);
    ctx.fillText('তোমার বাংলাদেশ কোথায়?', W / 2, H - 112);
    ctx.fillStyle = muted;
    font(ctx, 500, 36);
    ctx.fillText(host, W / 2, H - 58);
}

/** Square-card footer: the question on the left, site host on the right, along the bottom edge. */
export function footerSquare(ctx, host, { color = INK, muted = INK_2 } = {}) {
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    ctx.fillStyle = color;
    font(ctx, 700, 38);
    ctx.fillText('তোমার বাংলাদেশ কোথায়?', 60, S - 44);
    ctx.textAlign = 'right';
    ctx.fillStyle = muted;
    font(ctx, 500, 30);
    ctx.fillText(host, S - 60, S - 44);
}

/** Small deterministic hash, so decorative bits (seat, barcode) are stable per result. */
export function hash(text) {
    let h = 2166136261;
    for (const ch of String(text)) h = Math.imul(h ^ ch.codePointAt(0), 16777619);
    return h >>> 0;
}

/** Shadow settings ignore the canvas transform, so scale them for the small picker previews. */
export function shadow(ctx, color, blur, offsetY = 0) {
    const k = ctx.getTransform().a;
    ctx.shadowColor = color;
    ctx.shadowBlur = blur * k;
    ctx.shadowOffsetY = offsetY * k;
}
