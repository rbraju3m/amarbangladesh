import { bnDigits, possessive } from '../bn';
import { drawCover, fitText, font, footer, H, highlight, INK, INK_2, PAPER, RED, roundRect, stamp, W } from './common';

/** The original "Bangladesh vibe passport" page with a match stamp and badge stamps. */
export default function passport(ctx, result, { img, host }) {
    const loc = result.location;
    const accent = loc.accent;

    // Paper background with a soft accent wash.
    ctx.fillStyle = PAPER;
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

    footer(ctx, host);
}
