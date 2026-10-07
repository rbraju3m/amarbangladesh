// A short canvas confetti burst for the result screen. No-op when the user prefers reduced motion.
export function confetti(colors, { count = 140, duration = 2600 } = {}) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = Object.assign(document.createElement('canvas'), { ariaHidden: 'true' });
    canvas.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:60';
    document.body.append(canvas);

    const dpr = Math.min(devicePixelRatio || 1, 2);
    const w = (canvas.width = innerWidth * dpr);
    const h = (canvas.height = innerHeight * dpr);
    const ctx = canvas.getContext('2d');

    const pieces = Array.from({ length: count }, (_, i) => {
        const fromLeft = i % 2 === 0;
        const angle = (fromLeft ? -60 : -120) + (Math.random() - 0.5) * 50;
        const speed = (9 + Math.random() * 9) * dpr;
        return {
            x: fromLeft ? 0 : w,
            y: h * 0.7,
            vx: Math.cos((angle * Math.PI) / 180) * speed,
            vy: Math.sin((angle * Math.PI) / 180) * speed,
            size: (6 + Math.random() * 6) * dpr,
            spin: Math.random() * Math.PI,
            vspin: (Math.random() - 0.5) * 0.3,
            color: colors[i % colors.length],
        };
    });

    const start = performance.now();
    const frame = (now) => {
        const t = now - start;
        ctx.clearRect(0, 0, w, h);
        ctx.globalAlpha = Math.max(0, 1 - Math.max(0, t - duration * 0.6) / (duration * 0.4));
        for (const p of pieces) {
            p.vy += 0.32 * dpr;
            p.vx *= 0.985;
            p.x += p.vx;
            p.y += p.vy;
            p.spin += p.vspin;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.spin);
            ctx.fillStyle = p.color;
            ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
            ctx.restore();
        }
        if (t < duration) requestAnimationFrame(frame);
        else canvas.remove();
    };
    requestAnimationFrame(frame);
}
