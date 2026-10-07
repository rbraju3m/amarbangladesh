import { bnDigits, possessive } from '../bn';
import { fitText, font, footer, footerSquare, H, S, W } from './common';
import { fillBackground } from './themes';

/** Typography only: flag-like disc, huge place name, trait bars. */
export default function minimal(ctx, result, { host, t }) {
    const loc = result.location;
    const accent = t.accent;
    const INK = t.ink, INK_2 = t.ink2;

    fillBackground(ctx, t, W, H);

    // The disc from the flag, in the place colour, bleeding off the corner.
    ctx.fillStyle = accent;
    ctx.beginPath();
    ctx.arc(W - 150, 330, 330, 0, Math.PI * 2);
    ctx.fill();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    font(ctx, 500, 220);
    ctx.fillText(loc.emoji, W - 210, 380);

    const x = 90;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';

    // Match number.
    ctx.fillStyle = INK;
    font(ctx, 700, 150);
    ctx.fillText(`${bnDigits(result.match_pct)}%`, x, 330);
    ctx.fillStyle = INK_2;
    font(ctx, 500, 40);
    ctx.fillText('ভাইব ম্যাচ', x, 400);

    // Who + place.
    let y = 720;
    ctx.fillStyle = INK_2;
    font(ctx, 500, 52);
    if (result.name) {
        ctx.fillStyle = INK;
        fitText(ctx, possessive(result.name), W - 2 * x, 96, 700);
        ctx.fillText(possessive(result.name), x, y);
        y += 74;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 48);
        ctx.fillText('বাংলাদেশ হলো', x, y);
    } else {
        ctx.fillText('আমার বাংলাদেশ হলো', x, y + 40);
    }
    y += 250;
    ctx.fillStyle = accent;
    fitText(ctx, loc.name_bn, W - 2 * x, 250, 700);
    ctx.fillText(loc.name_bn, x, y);

    y += 100;
    ctx.fillStyle = INK;
    fitText(ctx, loc.title_bn, W - 2 * x, 60, 600);
    ctx.fillText(loc.title_bn, x, y);

    // Rule.
    y += 70;
    ctx.fillStyle = INK;
    ctx.fillRect(x, y, 120, 8);

    // Top traits as bars.
    y += 110;
    for (const t of (result.traits || []).slice(0, 4)) {
        ctx.fillStyle = INK;
        font(ctx, 600, 42);
        ctx.fillText(`${t.emoji} ${t.label}`, x, y);
        ctx.textAlign = 'right';
        ctx.fillStyle = INK_2;
        font(ctx, 500, 38);
        ctx.fillText(`${bnDigits(t.pct)}%`, W - x, y);
        ctx.textAlign = 'left';
        ctx.fillStyle = accent + '2e';
        ctx.fillRect(x, y + 26, W - 2 * x, 14);
        ctx.fillStyle = accent;
        ctx.fillRect(x, y + 26, (W - 2 * x) * Math.min(1, t.pct / 100), 14);
        y += 112;
    }

    footer(ctx, host, { color: INK, muted: INK_2 });
}

/** Square feed version: match number and disc on top, the place name, two trait bars. */
export function square(ctx, result, { host, t }) {
    const loc = result.location;
    const accent = t.accent;
    const INK = t.ink, INK_2 = t.ink2;

    fillBackground(ctx, t, S, S);

    ctx.fillStyle = accent;
    ctx.beginPath();
    ctx.arc(S - 110, 190, 250, 0, Math.PI * 2);
    ctx.fill();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    font(ctx, 500, 150);
    ctx.fillText(loc.emoji, S - 160, 230);

    const x = 70;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = INK;
    font(ctx, 700, 120);
    ctx.fillText(`${bnDigits(result.match_pct)}%`, x, 200);
    ctx.fillStyle = INK_2;
    font(ctx, 500, 36);
    ctx.fillText('ভাইব ম্যাচ', x, 258);

    let y = 430;
    if (result.name) {
        ctx.fillStyle = INK;
        fitText(ctx, possessive(result.name), S - 2 * x, 76, 700);
        ctx.fillText(possessive(result.name), x, y);
        y += 56;
        ctx.fillStyle = INK_2;
        font(ctx, 500, 40);
        ctx.fillText('বাংলাদেশ হলো', x, y);
    } else {
        ctx.fillStyle = INK_2;
        font(ctx, 500, 46);
        ctx.fillText('আমার বাংলাদেশ হলো', x, y + 40);
    }
    y += 190;
    ctx.fillStyle = accent;
    fitText(ctx, loc.name_bn, S - 2 * x, 190, 700);
    ctx.fillText(loc.name_bn, x, y);

    y += 80;
    ctx.fillStyle = INK;
    fitText(ctx, loc.title_bn, S - 2 * x, 50, 600);
    ctx.fillText(loc.title_bn, x, y);

    // Top two traits side by side.
    y += 100;
    const colW = (S - 2 * x - 60) / 2;
    (result.traits || []).slice(0, 2).forEach((tr, i) => {
        const cx = x + i * (colW + 60);
        ctx.textAlign = 'left';
        ctx.fillStyle = INK;
        fitText(ctx, `${tr.emoji} ${tr.label}`, colW - 100, 38, 600);
        ctx.fillText(`${tr.emoji} ${tr.label}`, cx, y);
        ctx.textAlign = 'right';
        ctx.fillStyle = INK_2;
        font(ctx, 500, 34);
        ctx.fillText(`${bnDigits(tr.pct)}%`, cx + colW, y);
        ctx.fillStyle = accent + '2e';
        ctx.fillRect(cx, y + 22, colW, 12);
        ctx.fillStyle = accent;
        ctx.fillRect(cx, y + 22, colW * Math.min(1, tr.pct / 100), 12);
    });

    footerSquare(ctx, host, { color: INK, muted: INK_2 });
}
