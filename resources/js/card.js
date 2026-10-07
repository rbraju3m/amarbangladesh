import { bnDigits, possessive } from './bn';

/**
 * Draws the 1080×1920 "Bangladesh vibe passport" story card in the browser.
 * Done client-side on purpose: the browser shapes Bangla conjuncts correctly, PHP GD does not.
 */
const W = 1080;
const H = 1920;
const FONT = '"Anek Bangla Variable", "Noto Sans Bengali", sans-serif';

const loadImage = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
    });

function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}

function fitText(ctx, text, maxWidth, size, weight) {
    let s = size;
    do {
        ctx.font = `${weight} ${s}px ${FONT}`;
        s -= 4;
    } while (ctx.measureText(text).width > maxWidth && s > 24);
}

function stamp(ctx, text, x, y, angle, color) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.font = `600 40px ${FONT}`;
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

export async function renderCard(result, { host = location.host } = {}) {
    await Promise.all([document.fonts.load(`700 100px ${FONT}`), document.fonts.load(`500 40px ${FONT}`)]).catch(() => {});

    const loc = result.location;
    const accent = loc.accent;
    const canvas = Object.assign(document.createElement('canvas'), { width: W, height: H });
    const ctx = canvas.getContext('2d');

    // Paper background with a soft accent wash.
    ctx.fillStyle = '#fbf8f1';
    ctx.fillRect(0, 0, W, H);
    const wash = ctx.createLinearGradient(0, 0, 0, H);
    wash.addColorStop(0, accent + '33');
    wash.addColorStop(1, accent + '08');
    ctx.fillStyle = wash;
    ctx.fillRect(0, 0, W, H);

    // Passport page.
    const px = 70, py = 110, pw = W - 140, ph = H - 300;
    ctx.save();
    ctx.shadowColor = 'rgba(20,33,27,.18)';
    ctx.shadowBlur = 50;
    ctx.shadowOffsetY = 18;
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, px, py, pw, ph, 48);
    ctx.fill();
    ctx.restore();

    // Illustration window.
    const img = await loadImage(loc.illustration);
    ctx.save();
    roundRect(ctx, px + 30, py + 30, pw - 60, 640, 32);
    ctx.clip();
    ctx.fillStyle = accent;
    ctx.fillRect(px + 30, py + 30, pw - 60, 640);
    if (img) {
        const iw = pw - 60, ih = 640, scale = Math.max(iw / 400, ih / 300);
        ctx.drawImage(img, px + 30 + (iw - 400 * scale) / 2, py + 30 + (ih - 300 * scale) / 2, 400 * scale, 300 * scale);
    }
    ctx.restore();

    // Header ribbon on the illustration.
    ctx.fillStyle = 'rgba(255,255,255,.92)';
    roundRect(ctx, px + 60, py + 60, 460, 64, 32);
    ctx.fill();
    ctx.fillStyle = '#14211b';
    ctx.font = `600 32px ${FONT}`;
    ctx.textBaseline = 'middle';
    ctx.fillText('🇧🇩  বাংলাদেশ ভাইব পাসপোর্ট', px + 88, py + 94);

    // Owner line.
    let y = py + 760;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = '#4b5a52';
    ctx.font = `500 46px ${FONT}`;
    ctx.fillText(result.name ? `${possessive(result.name)} বাংলাদেশ` : 'আমার বাংলাদেশ', W / 2, y);

    // Location name.
    y += 170;
    ctx.fillStyle = accent;
    fitText(ctx, `${loc.name_bn} ${loc.emoji}`, pw - 120, 168, 700);
    ctx.fillText(`${loc.name_bn} ${loc.emoji}`, W / 2, y);

    // Personality title.
    y += 120;
    ctx.fillStyle = '#14211b';
    fitText(ctx, loc.title_bn, pw - 140, 62, 600);
    ctx.fillText(loc.title_bn, W / 2, y);

    // Match stamp.
    y += 175;
    ctx.save();
    ctx.translate(W / 2, y);
    ctx.rotate(-0.06);
    ctx.strokeStyle = '#e03a3e';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(0, 0, 120, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(0, 0, 104, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = '#e03a3e';
    ctx.textAlign = 'center';
    ctx.font = `700 92px ${FONT}`;
    ctx.fillText(`${bnDigits(result.match_pct)}%`, 0, 22);
    ctx.font = `600 30px ${FONT}`;
    ctx.fillText('ভাইব ম্যাচ', 0, 70);
    ctx.restore();

    // Badge stamps, two rows.
    const badges = (loc.badges || []).slice(0, 4);
    const angles = [-0.05, 0.04, 0.03, -0.04];
    y += 220;
    badges.forEach((badge, i) => {
        const row = Math.floor(i / 2);
        const x = i % 2 === 0 ? W / 2 - 205 : W / 2 + 205;
        stamp(ctx, badge, x, y + row * 110, angles[i], i % 2 ? '#14211b' : accent);
    });

    // Footer CTA outside the passport.
    ctx.textAlign = 'center';
    ctx.fillStyle = '#14211b';
    ctx.font = `700 54px ${FONT}`;
    ctx.fillText('তোমার বাংলাদেশ কোথায়?', W / 2, H - 112);
    ctx.fillStyle = '#4b5a52';
    ctx.font = `500 36px ${FONT}`;
    ctx.fillText(host, W / 2, H - 58);

    return canvas;
}

export const canvasToBlob = (canvas) => new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
