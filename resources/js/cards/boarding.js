import { bnDigits } from '../bn';
import { drawCover, fitText, font, footer, H, hash, INK, INK_2, roundRect, W } from './common';

/** An airline boarding pass: "মন → {place}", with seat, gate and a barcode stub. */
export default function boarding(ctx, result, { img, host }) {
    const loc = result.location;
    const accent = loc.accent;
    const h = hash(result.code || loc.slug);

    // Dark backdrop with a glow in the place colour.
    ctx.fillStyle = INK;
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
    ctx.shadowColor = 'rgba(0,0,0,.35)';
    ctx.shadowBlur = 60;
    ctx.shadowOffsetY = 20;
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
    ctx.fillText('✈  বাংলাদেশ ভাইব এয়ার', tx + 48, ty + 62);
    ctx.textAlign = 'right';
    font(ctx, 600, 30);
    ctx.fillText('বোর্ডিং পাস', tx + tw - 48, ty + 62);

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
    label('থেকে', tx + 48, y);
    label('গন্তব্য', tx + tw - 48, y, 'right');
    y += 100;
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    font(ctx, 700, 96);
    ctx.fillText('মন', tx + 48, y);
    const fromW = ctx.measureText('মন').width;
    ctx.textAlign = 'right';
    ctx.fillStyle = accent;
    fitText(ctx, loc.name_bn, tw - 96 - fromW - 200, 96, 700);
    const toW = ctx.measureText(loc.name_bn).width;
    ctx.fillText(loc.name_bn, tx + tw - 48, y);

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
    label('যাত্রী', tx + 48, y);
    y += 92;
    ctx.textAlign = 'left';
    ctx.fillStyle = INK;
    const passenger = result.name || 'তুমি';
    fitText(ctx, passenger, tw - 96, 84, 700);
    ctx.fillText(passenger, tx + 48, y);

    // Match / seat / gate.
    y += 90;
    const col = (tw - 96) / 3;
    const seat = `${bnDigits(1 + (h % 32))}${'ABCDEF'[h % 6]}`;
    [['ভাইব ম্যাচ', `${bnDigits(result.match_pct)}%`], ['আসন', seat], ['গেট', loc.emoji]].forEach(([k, v], i) => {
        const x = tx + 48 + col * i;
        label(k, x, y);
        ctx.fillStyle = i === 0 ? '#e03a3e' : INK;
        ctx.textAlign = 'left';
        font(ctx, 700, 68);
        ctx.fillText(v, x, y + 80);
    });

    // Class = the personality title.
    y += 170;
    label('ক্লাস', tx + 48, y);
    ctx.fillStyle = INK;
    ctx.textAlign = 'left';
    fitText(ctx, loc.title_bn, tw - 96, 54, 600);
    ctx.fillText(loc.title_bn, tx + 48, y + 66);

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
