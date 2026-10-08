/** localStorage that never throws (private mode, blocked storage) and stores JSON. */
export const store = {
    get(key, fallback) {
        try {
            return JSON.parse(localStorage.getItem(key)) ?? fallback;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {}
    },
};

export const uuid = () =>
    crypto.randomUUID?.() ??
    '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16));

/** The random id that ties a browser's analytics events together (shared by the quiz and the community). */
export function visitorId() {
    const id = store.get('bd.visitor') || uuid();
    store.set('bd.visitor', id);
    return id;
}
