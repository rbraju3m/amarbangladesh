/**
 * Live balance preview for the question and location editors: replays every answer combination
 * in the browser with the form's unsaved values and shows each place's share of wins next to the
 * saved state. Mirrors App\Quiz\Scorer::rank() (cosine + BONUS_WEIGHT × bonus, ties to the
 * earlier place); the Balance page stays the authoritative check.
 */
const LIMIT = 2_000_000;

function simulate(data, locations, questions) {
    const D = data.traitKeys.length;
    const L = locations.length;
    const unit = locations.map((l) => {
        const n = Math.hypot(...l.profile);
        return Float64Array.from(l.profile, (x) => (n ? x / n : 0));
    });
    const Q = questions
        .filter((q) => q.length)
        .map((q) => q.map((o) => ({ w: Float64Array.from(o.weights), b: Float64Array.from(locations, (l) => (o.bonus[l.slug] || 0) * data.bonusWeight) })));
    const total = Q.reduce((n, q) => n * q.length, Q.length ? 1 : 0);
    if (!L || !total || total > LIMIT) return { total, share: null };

    const wins = new Array(L).fill(0);
    const vs = Q.map(() => new Float64Array(D));
    const bs = Q.map(() => new Float64Array(L));
    const zeroV = new Float64Array(D), zeroB = new Float64Array(L);

    const walk = (depth, v, b) => {
        if (depth === Q.length) {
            const norm = Math.hypot(...v);
            let best = -Infinity, at = 0;
            for (let l = 0; l < L; l++) {
                let dot = 0;
                for (let d = 0; d < D; d++) dot += v[d] * unit[l][d];
                const s = (norm ? dot / norm : 0) + b[l];
                if (s > best) (best = s), (at = l);
            }
            wins[at]++;
            return;
        }
        const nv = vs[depth], nb = bs[depth];
        for (const o of Q[depth]) {
            for (let d = 0; d < D; d++) nv[d] = v[d] + o.w[d];
            for (let l = 0; l < L; l++) nb[l] = b[l] + o.b[l];
            walk(depth + 1, nv, nb);
        }
    };
    walk(0, zeroV, zeroB);

    return { total, share: Object.fromEntries(locations.map((l, i) => [l.slug, (100 * wins[i]) / total])) };
}

const num = (el) => (el ? Number(el.value) || 0 : 0);

export function initBalancePreview() {
    const script = document.getElementById('balance-data');
    const panel = document.querySelector('[data-balance-preview]');
    if (!script || !panel) return;
    const data = JSON.parse(script.textContent);
    const form = panel.closest('form');
    const rows = panel.querySelector('[data-balance-rows]');
    const status = panel.querySelector('[data-balance-status]');
    const chip = form.querySelector('[data-balance-chip]');
    const { ctx, meta } = data;
    const slugs = Object.keys(meta);

    // Saved state: active places in sort order, active questions as option lists keyed by question id.
    const savedLocations = slugs.filter((s) => data.locations[s]).map((s) => ({ slug: s, profile: data.locations[s].profile }));
    const savedQuestions = Object.fromEntries(Object.entries(data.questions).map(([id, opts]) => [id, Object.values(opts)]));

    // The same, with this form's unsaved values swapped in.
    const current = () => {
        const active = form.querySelector('[name=is_active]')?.checked ?? true;
        if (ctx.kind === 'location') {
            const profile = data.traitKeys.map((k) => num(form.querySelector(`[name="profile[${k}]"]`)));
            const locations = slugs
                .filter((s) => (s === ctx.slug ? active : data.locations[s]))
                .map((s) => ({ slug: s, profile: s === ctx.slug ? profile : data.locations[s].profile }));
            return [locations, Object.values(savedQuestions)];
        }
        const questions = { ...savedQuestions };
        const key = ctx.id ?? 'new';
        delete questions[key];
        if (active) {
            const options = [...form.querySelectorAll('[data-answer]')]
                .filter((card) => card.querySelector('[name$="[is_active]"]')?.checked !== false && !card.querySelector('[name$="[_delete]"]')?.checked)
                .map((card) => ({
                    weights: data.traitKeys.map((k) => num(card.querySelector(`[name$="[weights][${k}]"]`))),
                    bonus: Object.fromEntries(slugs.map((s) => [s, num(card.querySelector(`[name$="[bonus][${s}]"]`))])),
                }));
            if (options.length) questions[key] = options;
        }
        return [savedLocations, Object.values(questions)];
    };

    const baseline = simulate(data, savedLocations, Object.values(savedQuestions));
    const max = Math.max(25, data.max + 5);

    const render = () => {
        const [locations, questions] = current();
        const t0 = performance.now();
        const now = simulate(data, locations, questions);
        if (!now.share) {
            rows.innerHTML = `<p class="text-sm text-ink-2">${now.total > LIMIT ? `${now.total.toLocaleString()} combinations is too many to preview live; save and open the Balance page.` : 'Nothing to simulate yet.'}</p>`;
            status.textContent = '';
            if (chip) chip.hidden = true;
            return;
        }
        const out = locations.filter((l) => now.share[l.slug] < data.min || now.share[l.slug] > data.max);
        rows.innerHTML = locations
            .map(({ slug }) => {
                const v = now.share[slug];
                const was = baseline.share?.[slug];
                const delta = was === undefined ? null : v - was;
                const bad = v < data.min || v > data.max;
                const deltaText = delta === null ? '<span class="text-ink-2">new</span>' : Math.abs(delta) < 0.05 ? '' : `<span class="${delta > 0 ? 'text-accent' : 'text-flag-red'}">${delta > 0 ? '▲' : '▼'} ${Math.abs(delta).toFixed(1)}</span>`;
                return `<div class="grid grid-cols-[5.5rem_1fr_auto] items-center gap-2 text-xs @md:grid-cols-[7.5rem_1fr_6.5rem] @md:gap-3 @md:text-sm ${slug === ctx.slug ? 'font-bold' : ''}">
                    <span class="truncate">${meta[slug].emoji} ${meta[slug].name}</span>
                    <span class="relative h-2.5 rounded-full bg-paper-2">
                        <span class="absolute inset-y-0 rounded-full bg-accent/15" style="left:${(data.min / max) * 100}%;width:${((data.max - data.min) / max) * 100}%"></span>
                        ${was === undefined ? '' : `<span class="absolute -inset-y-0.5 w-0.5 rounded bg-ink-2/50" style="left:${(Math.min(was, max) / max) * 100}%" title="saved: ${was.toFixed(1)}%"></span>`}
                        <span class="absolute inset-y-0 left-0 rounded-full transition-[width] duration-200 ${bad ? 'bg-flag-red' : 'bg-accent'}" style="width:${(Math.min(v, max) / max) * 100}%"></span>
                    </span>
                    <span class="flex justify-end gap-2 tabular-nums"><b class="${bad ? 'text-flag-red' : ''}">${v.toFixed(1)}%</b>${deltaText}</span>
                </div>`;
            })
            .join('');
        const ms = Math.round(performance.now() - t0);
        status.innerHTML = out.length
            ? `<span class="pill bg-flag-red/10 text-flag-red">⚠ ${out.map((l) => meta[l.slug].emoji + ' ' + meta[l.slug].name).join(', ')} outside ${data.min}–${data.max}%</span>`
            : `<span class="pill bg-accent/10 text-accent">✓ All ${locations.length} places in ${data.min}–${data.max}%</span>`;
        status.title = `${now.total.toLocaleString()} combinations in ${ms} ms`;
        if (chip) {
            chip.hidden = false;
            chip.className = `pill ${out.length ? 'bg-flag-red/10 text-flag-red' : 'bg-accent/10 text-accent'}`;
            chip.textContent = out.length ? `⚖ ${out.length} place${out.length > 1 ? 's' : ''} out of balance` : '⚖ Balanced';
        }
    };

    let timer;
    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(() => requestAnimationFrame(render), 120);
    };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    form.addEventListener('click', (e) => e.target.closest('[data-add-answer]') && schedule());
    panel.hidden = false;
    render();
}
