import { bnDigits, possessive } from '../bn';
import { drawCover, fitText, font, H, roundRect, shadow, W } from './common';

/** Full-bleed illustration fading into the place colour, with big white type. */
export default function poster(ctx, result, { img, host, t }) {
    const loc = result.location;
    // Dark themes paint with their own deep background colour; the rest with the accent.
    const accent = t.dark ? t.bg[1] : t.accent;
    const INK = t.cardInk;

    ctx.fillStyle = accent;
    ctx.fillRect(0, 0, W, H);
    drawCover(ctx, img, 0, 0, W, 1250, accent);

    // Fade the art into the accent, then darken the bottom so white text always reads.
    const fade = ctx.createLinearGradient(0, 700, 0, 1300);
    fade.addColorStop(0, accent + '00');
    fade.addColorStop(1, accent);
    ctx.fillStyle = fade;
    ctx.fillRect(0, 700, W, 600);
    const shade = ctx.createLinearGradient(0, 900, 0, H);
    shade.addColorStop(0, 'rgba(0,0,0,0)');
    shade.addColorStop(1, 'rgba(0,0,0,.45)');
    ctx.fillStyle = shade;
    ctx.fillRect(0, 900, W, H - 900);

    // Top chips: brand and match %.
    ctx.textBaseline = 'middle';
    ctx.fillStyle = 'rgba(255,255,255,.92)';
    roundRect(ctx, 64, 72, 380, 72, 36);
    ctx.fill();
    ctx.fillStyle = INK;
    font(ctx, 600, 32);
    ctx.textAlign = 'left';
    ctx.fillText('🇧🇩  আমার বাংলাদেশ', 96, 110);

    const pct = `${bnDigits(result.match_pct)}% ম্যাচ`;
    font(ctx, 700, 36);
    const pw = ctx.measureText(pct).width + 64;
    ctx.fillStyle = INK;
    roundRect(ctx, W - 64 - pw, 72, pw, 72, 36);
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.fillText(pct, W - 64 - pw / 2, 110);

    // Headline block, laid out bottom-up from the footer so it never collides.
    const x0 = 70;
    font(ctx, 600, 36);
    const rows = [[]];
    let rowW = 0;
    for (const badge of (loc.badges || []).slice(0, 4)) {
        const w = ctx.measureText(badge).width + 52;
        if (rowW + w > W - 2 * x0 && rows.at(-1).length) {
            rows.push([]);
            rowW = 0;
        }
        rows.at(-1).push([badge, w]);
        rowW += w + 16;
    }
    const badgesTop = H - 230 - rows.length * 84;

    ctx.strokeStyle = 'rgba(255,255,255,.85)';
    ctx.fillStyle = '#fff';
    ctx.lineWidth = 3;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    rows.forEach((row, r) => {
        let x = x0;
        for (const [badge, w] of row) {
            roundRect(ctx, x, badgesTop + r * 84, w, 66, 33);
            ctx.stroke();
            ctx.fillText(badge, x + 26, badgesTop + r * 84 + 35);
            x += w + 16;
        }
    });

    ctx.textBaseline = 'alphabetic';
    let y = badgesTop - 40;
    fitText(ctx, `${loc.emoji} ${loc.title_bn}`, W - 2 * x0, 60, 600);
    ctx.fillText(`${loc.emoji} ${loc.title_bn}`, x0, y);

    shadow(ctx, 'rgba(0,0,0,.25)', 24);
    y -= 110;
    fitText(ctx, loc.name_bn, W - 2 * x0, 230, 700);
    ctx.fillText(loc.name_bn, x0, y);

    y -= 245;
    ctx.fillStyle = 'rgba(255,255,255,.88)';
    if (result.name) {
        font(ctx, 500, 50);
        ctx.fillText('বাংলাদেশ হলো', x0, y);
        ctx.fillStyle = '#fff';
        fitText(ctx, possessive(result.name), W - 2 * x0, 110, 700);
        ctx.fillText(possessive(result.name), x0, y - 76);
    } else {
        font(ctx, 500, 56);
        ctx.fillText('আমার বাংলাদেশ হলো', x0, y);
    }
    ctx.shadowBlur = 0;

    // Footer on the dark band.
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = '#fff';
    font(ctx, 700, 54);
    ctx.fillText('তোমার বাংলাদেশ কোথায়?', W / 2, H - 112);
    ctx.fillStyle = 'rgba(255,255,255,.75)';
    font(ctx, 500, 36);
    ctx.fillText(host, W / 2, H - 58);
}
