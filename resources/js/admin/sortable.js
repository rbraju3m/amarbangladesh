/**
 * Drag-and-drop reordering for lists marked `data-sortable="<save url>"`: drag the `[data-handle]`
 * with mouse or touch, or focus it and press ↑/↓. Each change is POSTed as the full id list.
 * Without JS the per-row ↑/↓ forms still work.
 */
function toast(text, ok = true) {
    const el = document.createElement('div');
    el.setAttribute('role', 'status');
    el.className = 'fixed right-4 bottom-4 z-50 flex items-center gap-2 rounded-2xl bg-ink px-4 py-3 text-sm font-semibold text-paper shadow-xl transition duration-300';
    el.innerHTML = `<span class="grid size-5 place-items-center rounded-full ${ok ? 'bg-flag-green' : 'bg-flag-red'} text-xs text-white">${ok ? '✓' : '!'}</span>`;
    el.append(text);
    document.body.append(el);
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
    }, 2200);
}

export function initSortable() {
    document.querySelectorAll('[data-sortable]').forEach((list) => {
        const items = () => [...list.querySelectorAll(':scope > [data-id]')];
        const order = () => items().map((li) => li.dataset.id);
        list.querySelectorAll('[data-handle]').forEach((h) => (h.hidden = false));
        list.classList.add('is-sortable'); // phones drop the ↑/↓ buttons once dragging works
        document.querySelector('[data-sortable-hint]')?.removeAttribute('hidden');
        document.querySelector('[data-sortable-hint]')?.classList.remove('hidden');

        const renumber = () =>
            items().forEach((li, i, all) => {
                li.querySelector('[data-num]').textContent = i + 1;
                const up = li.querySelector('[data-up]'), down = li.querySelector('[data-down]');
                if (up) up.disabled = i === 0;
                if (down) down.disabled = i === all.length - 1;
            });

        let saved = order().join();
        const save = async () => {
            renumber();
            if (order().join() === saved) return;
            try {
                const res = await fetch(list.dataset.sortable, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': list.dataset.csrf },
                    body: JSON.stringify({ ids: order().map(Number) }),
                });
                if (!res.ok) throw new Error(res.status);
                saved = order().join();
                toast('Question order saved');
            } catch {
                toast('Could not save the order — reloading', false);
                setTimeout(() => location.reload(), 1200);
            }
        };

        // Pointer dragging: the row follows the pointer and swaps with a neighbour once past its middle.
        let drag = null;
        list.addEventListener('pointerdown', (e) => {
            const handle = e.target.closest('[data-handle]');
            if (!handle || e.button > 0) return;
            e.preventDefault();
            const item = handle.closest('[data-id]');
            try {
                handle.setPointerCapture(e.pointerId);
            } catch {}
            drag = { item, handle, startY: e.clientY };
            item.classList.add('is-dragging');
        });
        list.addEventListener('pointermove', (e) => {
            if (!drag) return;
            const { item } = drag;
            const prev = item.previousElementSibling, next = item.nextElementSibling;
            const mid = (el) => {
                const r = el.getBoundingClientRect();
                return r.top + r.height / 2;
            };
            const before = item.getBoundingClientRect().top - (Number.parseFloat(item.style.translate?.split(' ')[1]) || 0);
            if (prev && e.clientY < mid(prev)) list.insertBefore(item, prev);
            else if (next && e.clientY > mid(next)) list.insertBefore(next, item);
            const after = item.getBoundingClientRect().top - (Number.parseFloat(item.style.translate?.split(' ')[1]) || 0);
            drag.startY += after - before;
            item.style.translate = `0 ${e.clientY - drag.startY}px`;
        });
        const drop = () => {
            if (!drag) return;
            drag.item.style.translate = '';
            drag.item.classList.remove('is-dragging');
            drag.handle.focus({ preventScroll: true });
            drag = null;
            save();
        };
        list.addEventListener('pointerup', drop);
        list.addEventListener('pointercancel', drop);

        // Keyboard: ↑/↓ on a focused handle moves the row.
        list.addEventListener('keydown', (e) => {
            const handle = e.target.closest('[data-handle]');
            if (!handle || !['ArrowUp', 'ArrowDown'].includes(e.key)) return;
            e.preventDefault();
            const item = handle.closest('[data-id]');
            if (e.key === 'ArrowUp' && item.previousElementSibling) list.insertBefore(item, item.previousElementSibling);
            if (e.key === 'ArrowDown' && item.nextElementSibling) list.insertBefore(item.nextElementSibling, item);
            handle.focus();
            save();
        });
    });
}
