/** Small progressive enhancements for the admin pages; every page works without them. */
import { initBalancePreview } from './admin/balance-preview';
import { initPreviews } from './admin/previews';
import { initSortable } from './admin/sortable';

// Toast: fade out the "saved" message after a few seconds.
document.querySelectorAll('[data-toast]').forEach((el) => {
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
        setTimeout(() => el.remove(), 400);
    }, 3500);
});

// Trend chart: crosshair + tooltip for the nearest day.
document.querySelectorAll('[data-trend]').forEach((wrap) => {
    const svg = wrap.querySelector('svg');
    const tip = wrap.querySelector('[data-tip]');
    const line = svg.querySelector('[data-cross]');
    const rows = JSON.parse(wrap.dataset.trend);
    const { left, right } = JSON.parse(wrap.dataset.plot);
    const series = [...wrap.querySelectorAll('[data-series]')].map((el) => ({ key: el.dataset.series, label: el.dataset.label, color: el.dataset.color }));

    const show = (clientX) => {
        const box = svg.getBoundingClientRect();
        const vb = svg.viewBox.baseVal;
        const x = ((clientX - box.left) / box.width) * vb.width;
        const step = rows.length > 1 ? (right - left) / (rows.length - 1) : 0;
        const i = Math.max(0, Math.min(rows.length - 1, step ? Math.round((x - left) / step) : 0));
        const px = left + i * step;
        line.setAttribute('x1', px);
        line.setAttribute('x2', px);
        line.style.opacity = '1';
        const r = rows[i];
        tip.innerHTML =
            `<div class="mb-1 font-bold">${r.label}</div>` +
            series.map((s) => `<div class="flex items-center justify-between gap-4"><span class="flex items-center gap-1.5"><i class="inline-block size-2.5 rounded-full" style="background:${s.color}"></i>${s.label}</span><b class="tabular-nums">${r[s.key]}</b></div>`).join('');
        tip.hidden = false;
        const tx = (px / vb.width) * box.width;
        tip.style.left = `${Math.min(Math.max(tx - tip.offsetWidth / 2, 0), box.width - tip.offsetWidth)}px`;
    };
    const hide = () => {
        tip.hidden = true;
        line.style.opacity = '0';
    };
    svg.addEventListener('pointermove', (e) => show(e.clientX));
    svg.addEventListener('pointerleave', hide);
    svg.addEventListener('focus', () => show(svg.getBoundingClientRect().right - 1));
    svg.addEventListener('blur', hide);
});

// Sliders: live value badge, coloured by sign.
const paintSlider = (input) => {
    const out = input.parentElement.querySelector('output');
    const v = Number(input.value);
    if (out) {
        out.textContent = v > 0 && Number(input.min) < 0 ? `+${v}` : `${v}`;
        out.dataset.sign = v > 0 ? 'pos' : v < 0 ? 'neg' : 'zero';
    }
    const min = Number(input.min), max = Number(input.max);
    const zero = ((0 - min) / (max - min)) * 100, at = ((v - min) / (max - min)) * 100;
    const color = v < 0 ? 'var(--red)' : 'var(--accent)';
    input.style.background = `linear-gradient(to right, var(--paper-2) ${Math.min(zero, at)}%, ${color} ${Math.min(zero, at)}%, ${color} ${Math.max(zero, at)}%, var(--paper-2) ${Math.max(zero, at)}%)`;
};
document.addEventListener('input', (e) => {
    if (e.target.matches('input[type=range][data-slider]')) paintSlider(e.target);
});
document.querySelectorAll('input[type=range][data-slider]').forEach(paintSlider);

// Answer cards: keep the summary line in step with the label/emoji fields.
document.addEventListener('input', (e) => {
    const card = e.target.closest('[data-answer]');
    if (!card) return;
    const emoji = card.querySelector('[data-emoji]')?.value || '•';
    const label = card.querySelector('[data-label]')?.value || 'New answer';
    card.querySelector('[data-summary]').textContent = `${emoji}  ${label}`;
});

// "+ Add answer": clone the hidden template with the next index.
document.querySelectorAll('[data-add-answer]').forEach((btn) => {
    const list = document.querySelector(btn.dataset.addAnswer);
    const tpl = document.getElementById('answer-template');
    const max = Number(btn.dataset.max || 6);
    const sync = () => (btn.hidden = list.querySelectorAll('[data-answer]').length >= max);
    btn.addEventListener('click', () => {
        const index = Number(list.dataset.next);
        list.dataset.next = index + 1;
        list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__INDEX__', index));
        const card = list.lastElementChild;
        card.querySelectorAll('input[type=range][data-slider]').forEach(paintSlider);
        card.open = true;
        card.querySelector('[data-label]')?.focus();
        sync();
    });
    sync();
});

// Colour fields: keep the picker and the hex text box in step.
document.querySelectorAll('[data-color-pair]').forEach((wrap) => {
    const [picker, text] = wrap.querySelectorAll('input');
    picker.addEventListener('input', () => (text.value = picker.value));
    text.addEventListener('input', () => /^#[0-9a-f]{6}$/i.test(text.value) && (picker.value = text.value));
});

// Unsaved-changes guard on edit forms, plus a visible "unsaved" hint in the save bar.
document.querySelectorAll('form[data-dirty-guard]').forEach((form) => {
    let dirty = false;
    const hint = form.querySelector('[data-dirty-hint]');
    const mark = () => {
        dirty = true;
        if (hint) hint.hidden = false;
    };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('click', (e) => e.target.closest('[data-add-answer]') && mark());
    form.addEventListener('submit', () => (dirty = false));
    addEventListener('beforeunload', (e) => {
        if (dirty) e.preventDefault();
    });
});

initBalancePreview();
initSortable();
initPreviews();
