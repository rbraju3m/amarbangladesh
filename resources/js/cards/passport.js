import { bnDigits, possessive } from '../bn';
import { drawCover, fitText, font, footer, footerSquare, H, highlight, RED, roundRect, S, shadow, stamp, W } from './common';
import { fillBackground } from './themes';

/** The original "Bangladesh vibe passport" page with a match stamp and badge stamps. */
export default function passport(ctx, result, { img, host, t }) {
    const loc = result.location;
    const accent = t.cardAccent;
    const INK = t.cardInk, INK_2 = t.cardInk2;

    // Theme background with a soft accent wash.
    fillBackground(ctx, t, W, H);
    const wash = ctx.createLinearGradient(0, 0, 0, H);
    wash.addColorStop(0, accent + '33');
    wash.addColorStop(1, accent + '08');
    ctx.fillStyle = wash;
    ctx.fillRect(0, 0, W, H);

    // Passport page.
    const px = 70, py = 110, pw = W - 140, ph = H - 300;
    ctx.save();
    shadow(ctx, t.dark ? 'rgba(0,0,0,.4)' : 'rgba(20,33,27,.18)', 50, 18);
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, px, py, pw, ph, 48);
    ctx.fill();
    ctx.restore();

    // Illustration window (shorter when a name needs room for its headline).
    const ih = result.name ? 560 : 640;
    ctx.save();
    roundRect(ctx, px + 30, py + 30, pw - 60, ih, 32);
    ctx.clip();
    drawCover(ctx, img, px + 30, py + 30, pw - 60, ih, accent);
    ctx.restore();

    // Header ribbon on the illustration.
    ctx.fillStyle = 'rgba(255,255,255,.92)';
    roundRect(ctx, px + 60, py + 60, 460, 64, 32);
    ctx.fill();
    ctx.fillStyle = INK;
    font(ctx, 600, 32);
    ctx.textBaseline = 'middle';
    ctx.fillText('🇧🇩  বাংলাদেশ ভাইব পাসপোর্ট', px + 88, py + 94);

    // Owner line: a named card leads with the name as a highlighted headline.
    let y;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    if (result.name) {
        const who = possessive(result.name);
        y = py + 710;
        fitText(ctx, who, pw - 180, 120, 700);
        highlight(ctx, who, W / 2, y, accent + '38');
        ctx.fillStyle = INK;
        ctx.fillText(who, W / 2, y);
        y += 66;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 42);
        ctx.fillText('বাংলাদেশ হলো', W / 2, y);
        y += 160;
    } else {
        y = py + 760;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 46);
        ctx.fillText('আমার বাংলাদেশ', W / 2, y);
        y += 170;
    }

    // Location name.
    ctx.fillStyle = accent;
    fitText(ctx, `${loc.name_bn} ${loc.emoji}`, pw - 120, 168, 700);
    ctx.fillText(`${loc.name_bn} ${loc.emoji}`, W / 2, y);

    // Personality title.
    y += 120;
    ctx.fillStyle = INK;
    fitText(ctx, loc.title_bn, pw - 140, 62, 600);
    ctx.fillText(loc.title_bn, W / 2, y);

    // Match stamp.
    y += 175;
    ctx.save();
    ctx.translate(W / 2, y);
    ctx.rotate(-0.06);
    ctx.strokeStyle = RED;
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(0, 0, 120, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(0, 0, 104, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = RED;
    ctx.textAlign = 'center';
    font(ctx, 700, 92);
    ctx.fillText(`${bnDigits(result.match_pct)}%`, 0, 22);
    font(ctx, 600, 30);
    ctx.fillText('ভাইব ম্যাচ', 0, 70);
    ctx.restore();

    // Badge stamps, two rows.
    const badges = (loc.badges || []).slice(0, 4);
    const angles = [-0.05, 0.04, 0.03, -0.04];
    y += 220;
    badges.forEach((badge, i) => {
        const row = Math.floor(i / 2);
        const x = i % 2 === 0 ? W / 2 - 205 : W / 2 + 205;
        stamp(ctx, badge, x, y + row * 110, angles[i], i % 2 ? INK : accent);
    });

    footer(ctx, host, { color: t.ink, muted: t.ink2 });
}

/** Square feed version: the passport page laid sideways, art on the left and details on the right. */
export function square(ctx, result, { img, host, t }) {
    const loc = result.location;
    const accent = t.cardAccent;
    const INK = t.cardInk, INK_2 = t.cardInk2;

    fillBackground(ctx, t, S, S);
    const wash = ctx.createLinearGradient(0, 0, 0, S);
    wash.addColorStop(0, accent + '33');
    wash.addColorStop(1, accent + '08');
    ctx.fillStyle = wash;
    ctx.fillRect(0, 0, S, S);

    const px = 50, py = 50, pw = S - 100, ph = 900;
    ctx.save();
    shadow(ctx, t.dark ? 'rgba(0,0,0,.4)' : 'rgba(20,33,27,.18)', 44, 16);
    ctx.fillStyle = '#ffffff';
    roundRect(ctx, px, py, pw, ph, 44);
    ctx.fill();
    ctx.restore();

    const iw = 380;
    ctx.save();
    roundRect(ctx, px + 28, py + 28, iw, ph - 56, 30);
    ctx.clip();
    drawCover(ctx, img, px + 28, py + 28, iw, ph - 56, accent);
    ctx.restore();

    ctx.fillStyle = 'rgba(255,255,255,.92)';
    roundRect(ctx, px + 52, py + 52, 300, 58, 29);
    ctx.fill();
    ctx.fillStyle = INK;
    font(ctx, 600, 28);
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.fillText('🇧🇩  ভাইব পাসপোর্ট', px + 76, py + 83);

    // Details column.
    const cx = px + 28 + iw + (pw - iw - 56) / 2;
    const cw = pw - iw - 100;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    let y;
    if (result.name) {
        const who = possessive(result.name);
        y = 200;
        fitText(ctx, who, cw - 40, 88, 700);
        highlight(ctx, who, cx, y, accent + '38');
        ctx.fillStyle = INK;
        ctx.fillText(who, cx, y);
        y += 54;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 36);
        ctx.fillText('বাংলাদেশ হলো', cx, y);
    } else {
        y = 230;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 42);
        ctx.fillText('আমার বাংলাদেশ', cx, y);
    }
    y += 140;
    ctx.fillStyle = accent;
    fitText(ctx, `${loc.name_bn} ${loc.emoji}`, cw, 124, 700);
    ctx.fillText(`${loc.name_bn} ${loc.emoji}`, cx, y);
    y += 76;
    ctx.fillStyle = INK;
    fitText(ctx, loc.title_bn, cw, 44, 600);
    ctx.fillText(loc.title_bn, cx, y);

    // Match stamp.
    y += 160;
    ctx.save();
    ctx.translate(cx, y);
    ctx.rotate(-0.06);
    ctx.strokeStyle = RED;
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.arc(0, 0, 100, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.arc(0, 0, 86, 0, Math.PI * 2);
    ctx.stroke();
    ctx.fillStyle = RED;
    font(ctx, 700, 76);
    ctx.fillText(`${bnDigits(result.match_pct)}%`, 0, 18);
    font(ctx, 600, 26);
    ctx.fillText('ভাইব ম্যাচ', 0, 58);
    ctx.restore();

    // Two badge stamps.
    y += 180;
    (loc.badges || []).slice(0, 2).forEach((badge, i) => {
        stamp(ctx, badge, cx, y + i * 92, i ? 0.03 : -0.04, i ? INK : accent);
    });

    footerSquare(ctx, host, { color: t.ink, muted: t.ink2 });
}
