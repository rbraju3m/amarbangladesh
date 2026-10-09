import { t } from './i18n';
import { track } from './track';

/** Pages loaded on their own while scrolling; then a button, so the footer stays reachable. */
const AUTO_PAGES = 4;
/** A list restored on "back" is only trusted this long. */
const RESTORE_MS = 30 * 60 * 1000;

/**
 * Endless lists (feed, profiles, search): `x-data="infinite('#list-id')"` on a wrapper holding the
 * server-rendered `<a data-next href="?cursor=…">` link. Nearing the end fetches that next page's
 * normal HTML (the same cacheable page that works without JavaScript) and appends the items of the
 * same list that aren't on the page yet (each has `data-item="post:12"`). The loaded list and scroll
 * position survive leaving and coming back to the page in this tab.
 */
export default function infinite(listSelector) {
    return {
        state: 'idle', // idle | loading | error | done
        auto: 0,
        pages: 0,
        announcement: '',

        init() {
            this.list = document.querySelector(listSelector);
            this.link = this.$el.querySelector('a[data-next]');
            this.restore();
            addEventListener('pagehide', () => this.save());
            if (!this.link) return (this.state = 'done');
            if (!('IntersectionObserver' in window)) return;
            this.io = new IntersectionObserver(([e]) => e.isIntersecting && this.auto < AUTO_PAGES && this.more(true), { rootMargin: '600px' });
            this.io.observe(this.$el);
        },

        async more(auto = false) {
            if (this.state === 'loading' || !this.link) return;
            this.state = 'loading';
            try {
                const res = await fetch(this.link.href, { headers: { Accept: 'text/html' } });
                if (!res.ok) throw new Error(res.status);
                const page = new DOMParser().parseFromString(await res.text(), 'text/html');
                const seen = new Set([...this.list.querySelectorAll('[data-item]')].map((el) => el.dataset.item));
                let added = 0;
                for (const item of page.querySelectorAll(`${listSelector} > [data-item]`)) {
                    if (seen.has(item.dataset.item)) continue; // a post moved pages while we read
                    this.list.append(document.adoptNode(item));
                    added++;
                }
                const next = page.querySelector('a[data-next]')?.getAttribute('href');
                this.pages++;
                if (auto) this.auto++;
                track('feed_page_loaded', { meta: { page: this.pages + 1, auto: auto ? 1 : 0 } });
                this.announcement = t('আরও :nটি এসেছে', { n: added, count: added });
                if (next) {
                    this.link.href = next;
                    this.state = 'idle';
                    if (this.auto >= AUTO_PAGES) this.io?.disconnect();
                } else {
                    this.finish();
                }
            } catch {
                this.state = 'error';
                track('feed_load_failed', { meta: { page: this.pages + 2 } });
            }
        },

        finish() {
            this.io?.disconnect();
            this.link?.remove();
            this.link = null;
            this.state = 'done';
        },

        key() {
            return `bd.list:${location.pathname}${location.search}`;
        },

        save() {
            if (!this.pages) return;
            try {
                sessionStorage.setItem(this.key(), JSON.stringify({ html: this.list.innerHTML, next: this.link?.href ?? null, y: scrollY, auto: this.auto, pages: this.pages, at: Date.now() }));
            } catch {}
        },

        // Back to this page (not a fresh visit): put the loaded items and the scroll position back.
        restore() {
            let saved = null;
            try {
                saved = JSON.parse(sessionStorage.getItem(this.key()));
                sessionStorage.removeItem(this.key());
            } catch {}
            const nav = performance.getEntriesByType?.('navigation')[0]?.type;
            if (!saved || nav !== 'back_forward' || Date.now() - saved.at > RESTORE_MS) return;
            this.list.innerHTML = saved.html;
            Object.assign(this, { auto: saved.auto, pages: saved.pages });
            if (saved.next && this.link) this.link.href = saved.next;
            else this.finish();
            requestAnimationFrame(() => scrollTo(0, saved.y));
        },
    };
}
