import * as i18n from '../i18n';
import { drawCover, fitText, font, footer, footerSquare, H, hash, RED, roundRect, S, shadow, W } from './common';

/** An airline boarding pass: "মন → {place}", with seat, gate and a barcode stub. */
export default function boarding(ctx, result, { img, host, t }) {
    const loc = result.location;
    const accent = t.cardAccent;
    const INK = t.cardInk, INK_2 = t.cardInk2;
    const h = hash(result.code || loc.slug);

    // Dark backdrop (the theme's own, or its ink) with a glow in the accent.
    ctx.fillStyle = t.dark ? t.bg[0] : t.ink;
    ctx.fillRect(0, 0, W, H);
    const glow = ctx.createRadialGradient(W / 2, 520, 50, W / 2, 520, 1100);
    glow.addColorStop(0, accent + 'cc');
    glow.addColorStop(1, accent + '00');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, W, H);

    // Ticket body with two notches at the tear line.
    const tx = 70, ty = 120, tw = W - 140, th = 1610, tear = ty + 1300;
    ctx.save();
    // Clip out the notches so the backdrop shows through them (not transparency).
    ctx.beginPath();
    ctx.rect(0, 0, W, H);
    for (const cx of [tx, tx + tw]) {
        ctx.moveTo(cx + 36, tear);
        ctx.arc(cx, tear, 36, 0, Math.PI * 2);
    }
    ctx.clip('evenodd');
    shadow(ctx, 'rgba(0,0,0,.35)', 60, 20);
    ctx.fillStyle = '#fff';
    roundRect(ctx, tx, ty, tw, th, 44);
    ctx.fill();
    ctx.restore();

    // Header band.
    ctx.save();
    roundRect(ctx, tx, ty, tw, 120, [44, 44, 0, 0]);
    ctx.clip();
    ctx.fillStyle = accent;
    ctx.fillRect(tx, ty, tw, 120);
    ctx.restore();
    ctx.fillStyle = '#fff';
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    font(ctx, 700, 38);
    ctx.fillText(i18n.t('✈  বাংলাদেশ ভাইব এয়ার'), tx + 48, ty + 62);
    ctx.textAlign = 'right';
    font(ctx, 600, 30);
    ctx.fillText(i18n.t('বোর্ডিং পাস'), tx + tw - 48, ty + 62);

    // Illustration strip.
    const iy = ty + 150;
    ctx.save();
    roundRect(ctx, tx + 40, iy, tw - 80, 360, 28);
    ctx.clip();
    drawCover(ctx, img, tx + 40, iy, tw - 80, 360, accent);
    ctx.restore();

    // Route: from the heart to the place.
    const label = (text, x, y, align = 'left') => {
        ctx.textAlign = align;
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = INK_2;
        font(ctx, 500, 30);
        ctx.fillText(text, x, y);
    };
    let y = iy + 440;
    label(i18n.t('থেকে'), tx + 48, y);
    label(i18n.t('গন্তব্য'), tx + tw - 48, y, 'right');
    y += 100;
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    font(ctx, 700, 96);
    ctx.fillText(i18n.t('মন'), tx + 48, y);
    const fromW = ctx.measureText(i18n.t('মন')).width;
    ctx.textAlign = 'right';
    ctx.fillStyle = accent;
    fitText(ctx, loc.name, tw - 96 - fromW - 200, 96, 700);
    const toW = ctx.measureText(loc.name).width;
    ctx.fillText(loc.name, tx + tw - 48, y);

    // Flight path between the two.
    const ax = tx + 48 + fromW + 30, bx = tx + tw - 48 - toW - 30, ay = y - 32;
    ctx.strokeStyle = INK_2;
    ctx.lineWidth = 3;
    ctx.setLineDash([10, 10]);
    ctx.beginPath();
    ctx.moveTo(ax, ay);
    ctx.lineTo(bx, ay);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    font(ctx, 500, 48);
    ctx.fillStyle = '#fff';
    ctx.fillRect((ax + bx) / 2 - 34, ay - 30, 68, 60);
    ctx.fillStyle = INK;
    ctx.fillText('✈', (ax + bx) / 2, ay + 2);

    // Passenger.
    y += 110;
    label(i18n.t('যাত্রী'), tx + 48, y);
    y += 92;
    ctx.textAlign = 'left';
    ctx.fillStyle = INK;
    const passenger = result.name || i18n.t('তুমি');
    fitText(ctx, passenger, tw - 96, 84, 700);
    ctx.fillText(passenger, tx + 48, y);

    // Match / seat / gate.
    y += 90;
    const col = (tw - 96) / 3;
    const seat = `${i18n.num(1 + (h % 32))}${'ABCDEF'[h % 6]}`;
    [[i18n.t('ভাইব ম্যাচ'), `${i18n.num(result.match_pct)}%`], [i18n.t('আসন'), seat], [i18n.t('গেট'), loc.emoji]].forEach(([k, v], i) => {
        const x = tx + 48 + col * i;
        label(k, x, y);
        ctx.fillStyle = i === 0 ? RED : INK;
        ctx.textAlign = 'left';
        font(ctx, 700, 68);
        ctx.fillText(v, x, y + 80);
    });

    // Class = the personality title.
    y += 170;
    label(i18n.t('ক্লাস'), tx + 48, y);
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    fitText(ctx, loc.title, tw - 96, 54, 600);
    ctx.fillText(loc.title, tx + 48, y + 66);

    // Tear line.
    ctx.strokeStyle = '#d9d2c2';
    ctx.lineWidth = 4;
    ctx.setLineDash([14, 12]);
    ctx.beginPath();
    ctx.moveTo(tx + 50, tear);
    ctx.lineTo(tx + tw - 50, tear);
    ctx.stroke();
    ctx.setLineDash([]);

    // Barcode stub, stable per result.
    let bx2 = tx + 60, seed = h;
    const by = tear + 60, bh = 140, end = tx + tw - 60;
    ctx.fillStyle = INK;
    while (bx2 < end) {
        seed = Math.imul(seed ^ (seed >>> 15), 2246822519) >>> 0;
        const bar = 3 + (seed % 4) * 3;
        if (bx2 + bar > end) break;
        if (seed & 16) ctx.fillRect(bx2, by, bar, bh);
        bx2 += bar + 4;
    }
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = INK_2;
    font(ctx, 500, 30);
    ctx.fillText(result.code ? `#${result.code}` : '', W / 2, by + bh + 50);

    footer(ctx, host, { color: '#fff', muted: 'rgba(255,255,255,.7)' });
}

/** Square feed version: the ticket on its side, with the barcode stub on the right. */
export function square(ctx, result, { img, host, t }) {
    const loc = result.location;
    const accent = t.cardAccent;
    const INK = t.cardInk, INK_2 = t.cardInk2;
    const h = hash(result.code || loc.slug);

    ctx.fillStyle = t.dark ? t.bg[0] : t.ink;
    ctx.fillRect(0, 0, S, S);
    const glow = ctx.createRadialGradient(S / 2, 300, 50, S / 2, 300, 900);
    glow.addColorStop(0, accent + 'cc');
    glow.addColorStop(1, accent + '00');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, S, S);

    // Ticket with notches top and bottom of the vertical tear line.
    const tx = 50, ty = 50, tw = S - 100, th = 900, tear = tx + tw - 230;
    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, S, S);
    for (const cy of [ty, ty + th]) {
        ctx.moveTo(tear + 32, cy);
        ctx.arc(tear, cy, 32, 0, Math.PI * 2);
    }
    ctx.clip('evenodd');
    shadow(ctx, 'rgba(0,0,0,.35)', 50, 18);
    ctx.fillStyle = '#fff';
    roundRect(ctx, tx, ty, tw, th, 40);
    ctx.fill();
    ctx.restore();

    // Header band over the main part.
    ctx.save();
    roundRect(ctx, tx, ty, tear - 32 - tx, 96, [40, 0, 0, 0]);
    ctx.clip();
    ctx.fillStyle = accent;
    ctx.fillRect(tx, ty, tear - tx, 96);
    ctx.restore();
    ctx.fillStyle = '#fff';
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    font(ctx, 700, 34);
    ctx.fillText(i18n.t('✈  বাংলাদেশ ভাইব এয়ার'), tx + 40, ty + 50);

    const mw = tear - tx; // width of the main part
    const iy = ty + 120;
    ctx.save();
    roundRect(ctx, tx + 36, iy, mw - 72, 230, 24);
    ctx.clip();
    drawCover(ctx, img, tx + 36, iy, mw - 72, 230, accent);
    ctx.restore();

    const label = (text, x, y, align = 'left') => {
        ctx.textAlign = align;
        ctx.textBaseline = 'alphabetic';
        ctx.fillStyle = INK_2;
        font(ctx, 500, 26);
        ctx.fillText(text, x, y);
    };
    const L = tx + 40, R = tear - 40;

    // Route.
    let y = iy + 280;
    label(i18n.t('থেকে'), L, y);
    label(i18n.t('গন্তব্য'), R, y, 'right');
    y += 80;
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    font(ctx, 700, 78);
    ctx.fillText(i18n.t('মন'), L, y);
    const fromW = ctx.measureText(i18n.t('মন')).width;
    ctx.textAlign = 'right';
    ctx.fillStyle = accent;
    fitText(ctx, loc.name, R - L - fromW - 160, 78, 700);
    const toW = ctx.measureText(loc.name).width;
    ctx.fillText(loc.name, R, y);
    const ax = L + fromW + 24, bx = R - toW - 24, ay = y - 26;
    ctx.strokeStyle = INK_2;
    ctx.lineWidth = 3;
    ctx.setLineDash([9, 9]);
    ctx.beginPath();
    ctx.moveTo(ax, ay);
    ctx.lineTo(bx, ay);
    ctx.stroke();
    ctx.setLineDash([]);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    font(ctx, 500, 40);
    ctx.fillStyle = '#fff';
    ctx.fillRect((ax + bx) / 2 - 28, ay - 26, 56, 52);
    ctx.fillStyle = INK;
    ctx.fillText('✈', (ax + bx) / 2, ay + 2);

    // Passenger and match / seat.
    y += 70;
    label(i18n.t('যাত্রী'), L, y);
    y += 70;
    ctx.textAlign = 'left';
    ctx.fillStyle = INK;
    fitText(ctx, result.name || i18n.t('তুমি'), R - L, 66, 700);
    ctx.fillText(result.name || i18n.t('তুমি'), L, y);

    y += 64;
    const col = (R - L) / 3;
    const seat = `${i18n.num(1 + (h % 32))}${'ABCDEF'[h % 6]}`;
    [[i18n.t('ভাইব ম্যাচ'), `${i18n.num(result.match_pct)}%`], [i18n.t('আসন'), seat], [i18n.t('গেট'), loc.emoji]].forEach(([k, v], i) => {
        const x = L + col * i;
        label(k, x, y);
        ctx.fillStyle = i === 0 ? RED : INK;
        ctx.textAlign = 'left';
        font(ctx, 700, 56);
        ctx.fillText(v, x, y + 64);
    });

    // Tear line.
    ctx.strokeStyle = '#d9d2c2';
    ctx.lineWidth = 4;
    ctx.setLineDash([14, 12]);
    ctx.beginPath();
    ctx.moveTo(tear, ty + 50);
    ctx.lineTo(tear, ty + th - 50);
    ctx.stroke();
    ctx.setLineDash([]);

    // Stub: class, then a sideways barcode.
    const sx = tear + 36, sw = tx + tw - 36 - sx;
    label(i18n.t('ক্লাস'), sx, ty + 90);
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    fitText(ctx, loc.title, sw, 34, 600);
    ctx.fillText(loc.title, sx, ty + 134);

    let by = ty + 190, seed = h;
    const end = ty + th - 110;
    ctx.fillStyle = INK;
    while (by < end) {
        seed = Math.imul(seed ^ (seed >>> 15), 2246822519) >>> 0;
        const bar = 3 + (seed % 4) * 3;
        if (by + bar > end) break;
        if (seed & 16) ctx.fillRect(sx + 10, by, sw - 20, bar);
        by += bar + 4;
    }
    ctx.textAlign = 'center';
    ctx.fillStyle = INK_2;
    font(ctx, 500, 26);
    ctx.fillText(result.code ? `#${result.code}` : '', sx + sw / 2, ty + th - 56);

    footerSquare(ctx, host, { color: '#fff', muted: 'rgba(255,255,255,.7)' });
}
