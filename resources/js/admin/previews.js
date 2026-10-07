/** Live previews in the editors: the place's result card and a phone view of a question. */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const asset = (path) => (!path ? '' : /^(https?:)?\/\//.test(path) || path.startsWith('/') ? path : `/${path}`);

function locationPreview() {
    const card = document.querySelector('[data-loc-preview]');
    if (!card) return;
    const form = card.closest('form');
    const field = (name) => form.querySelector(`[name="${name}"]`);
    const img = card.querySelector('[data-bind-img]');
    // A mistyped path shows the accent colour instead of a broken-image icon.
    img.addEventListener('error', () => (img.style.opacity = '0'));
    img.addEventListener('load', () => (img.style.opacity = '1'));
    const update = () => {
        card.querySelectorAll('[data-bind]').forEach((el) => (el.textContent = field(el.dataset.bind)?.value ?? ''));
        const badges = (field('badges')?.value ?? '').split('\n').map((b) => b.trim()).filter(Boolean);
        card.querySelector('[data-bind-badges]').innerHTML = badges.map((b) => `<span class="chip !py-1 text-xs">${esc(b)}</span>`).join('');
        const src = asset(field('illustration')?.value.trim()) || img.dataset.default;
        if (img.getAttribute('src') !== src) img.src = src;
        const color = field('accent_color')?.value;
        if (/^#[0-9a-f]{6}$/i.test(color ?? '')) card.style.setProperty('--accent', color);
    };
    form.addEventListener('input', update);
}

function questionPreview() {
    const box = document.querySelector('[data-q-preview]');
    if (!box) return;
    const form = box.closest('form');
    const phone = box.querySelector('[data-q-phone]');
    const wide = matchMedia('(min-width: 80rem)');
    const syncOpen = () => wide.matches && (box.open = true);
    wide.addEventListener('change', syncOpen);
    if (!wide.matches) box.open = false;
    box.hidden = false;

    const spots = ['top:6%;left:8%', 'top:12%;right:4%', 'bottom:6%;left:14%', 'bottom:10%;right:10%'];
    const render = () => {
        const prompt = form.querySelector('[name=prompt_bn]')?.value.trim() || 'Your question…';
        const subtitle = form.querySelector('[name=subtitle_bn]')?.value.trim();
        const image = form.querySelector('[name=kind]')?.value === 'image';
        const options = [...form.querySelectorAll('[data-answer]')]
            .filter((c) => c.querySelector('[name$="[is_active]"]')?.checked !== false && !c.querySelector('[name$="[_delete]"]')?.checked)
            .map((c) => ({
                emoji: c.querySelector('[data-emoji]')?.value || '',
                label: c.querySelector('[data-label]')?.value || '…',
                image: asset(c.querySelector('[name$="[image]"]')?.value.trim()),
            }));

        const collage = image
            ? ''
            : `<div class="relative mx-auto mb-3 size-24 shrink-0 rounded-full bg-flag-green/10">${options
                  .slice(0, 4)
                  .map((o, i) => `<span class="absolute text-3xl" style="${spots[i]}">${esc(o.emoji)}</span>`)
                  .join('')}</div>`;
        const tiles = options
            .map((o) =>
                image
                    ? `<div class="relative flex min-h-24 items-end overflow-hidden rounded-2xl border-2 border-line bg-paper-2">${o.image ? `<img src="${esc(o.image)}" alt="" class="absolute inset-0 size-full object-cover">` : ''}<span class="relative w-full bg-gradient-to-t from-black/75 to-transparent px-1.5 pt-4 pb-1.5 text-[0.7rem] leading-tight font-semibold text-white">${esc(o.label)}</span></div>`
                    : `<div class="flex min-h-16 flex-col items-center justify-center gap-0.5 rounded-2xl border-2 border-line bg-card p-1.5 text-[0.72rem] leading-tight font-semibold"><span class="text-xl leading-none">${esc(o.emoji)}</span>${esc(o.label)}</div>`,
            )
            .join('');

        phone.innerHTML = `
            <div class="mb-2 flex items-center gap-1.5"><span class="grid size-5 place-items-center rounded-full border border-line text-[0.6rem]">‹</span>${'<span class="h-1 flex-1 rounded-full bg-line"></span>'.repeat(5)}</div>
            <div class="flex flex-1 flex-col items-center justify-center">
                ${collage}
                <p class="text-base leading-snug font-bold">${esc(prompt)}</p>
                ${subtitle ? `<p class="mt-1 text-xs text-ink-2">${esc(subtitle)}</p>` : ''}
            </div>
            <div class="grid grid-cols-2 gap-1.5">${tiles || '<p class="col-span-2 text-xs text-ink-2">No active answers</p>'}</div>`;
    };
    let frame;
    form.addEventListener('input', () => (cancelAnimationFrame(frame), (frame = requestAnimationFrame(render))));
    form.addEventListener('change', render);
    form.addEventListener('click', (e) => e.target.closest('[data-add-answer]') && requestAnimationFrame(render));
    render();
}

export function initPreviews() {
    locationPreview();
    questionPreview();
}
