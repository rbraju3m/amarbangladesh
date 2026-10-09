/**
 * `x-modal="open"` on a dialog: while `open` is truthy the page behind it doesn't scroll, Tab and
 * Shift+Tab stay inside the dialog, and focus moves in (to `[data-autofocus]` or the first control)
 * and back to whatever opened it once it closes.
 */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

let locks = 0;

// overflow:hidden on <html> holds the page on current browsers (iOS Safari 16+ included) without
// moving it, so screens that scroll to the top as a sheet closes still can.
function lockScroll() {
    if (!locks++) document.documentElement.style.overflow = 'hidden';
}

function unlockScroll() {
    if (!--locks) document.documentElement.style.overflow = '';
}

const visible = (el) => el.offsetWidth || el.offsetHeight || el.getClientRects().length;

export default function modal(Alpine) {
    Alpine.directive('modal', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const isOpen = evaluateLater(expression);
        let open = false;
        let opener = null;

        const onKey = (e) => {
            if (e.key !== 'Tab') return;
            const items = [...el.querySelectorAll(FOCUSABLE)].filter(visible);
            if (!items.length) return;
            const at = items.indexOf(document.activeElement); // -1: the panel itself, or somewhere outside
            if (e.shiftKey && at <= 0) {
                e.preventDefault();
                items[items.length - 1].focus();
            } else if (!e.shiftKey && (at === items.length - 1 || !el.contains(document.activeElement))) {
                e.preventDefault();
                items[0].focus();
            }
        };

        const close = () => {
            if (!open) return;
            open = false;
            el.removeEventListener('keydown', onKey);
            unlockScroll();
            if (opener?.isConnected) opener.focus({ preventScroll: true });
            opener = null;
        };

        effect(() =>
            isOpen((value) => {
                if (!!value === open) return;
                if (!value) return close();
                open = true;
                opener = document.activeElement;
                lockScroll();
                el.addEventListener('keydown', onKey);
                // Wait for x-show / x-if inside the dialog to render before looking for a control.
                setTimeout(() => {
                    if (!open || el.contains(document.activeElement)) return;
                    const target = el.querySelector('[data-autofocus]') ?? [...el.querySelectorAll(FOCUSABLE)].find(visible);
                    target?.focus({ preventScroll: true });
                }, 60);
            }),
        );
        cleanup(close);
    });
}
