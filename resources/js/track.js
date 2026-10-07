/**
 * Cookieless first-party analytics: events are queued and flushed in small batches
 * with sendBeacon, so tracking never delays a tap.
 */
const queue = [];
let context = { visitor_id: null, ref: null };
let timer = null;

export function setTrackingContext(next) {
    context = { ...context, ...next };
}

export function track(name, { result = null, meta = null } = {}) {
    queue.push({ name, result, meta });
    clearTimeout(timer);
    timer = setTimeout(flush, queue.length >= 10 ? 0 : 2500);
}

export function flush() {
    if (!queue.length) return;
    const body = JSON.stringify({ ...context, events: queue.splice(0, 20) });
    const sent = navigator.sendBeacon?.('/api/events', new Blob([body], { type: 'text/plain' }));
    if (!sent) {
        fetch('/api/events', { method: 'POST', body, keepalive: true, headers: { 'Content-Type': 'application/json' } }).catch(() => {});
    }
    if (queue.length) flush();
}

addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && flush());
addEventListener('pagehide', flush);
