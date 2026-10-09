import { withLang } from './i18n';

/**
 * A few community posts embedded in the quiz page (landing and result). Fetched when the block
 * nears the viewport, so the quiz's first paint and cached HTML are untouched. With `area` (a
 * function returning an area slug) it shows posts from that place; if there are none yet,
 * `areaCount` is 0 and the latest posts from anywhere are shown instead.
 */
export default function communityPreview(limit = 3, area = null) {
    return {
        html: '',
        count: null, // null = not loaded yet
        areaCount: null, // posts found for the area; null without an area
        init() {
            let loaded = false;
            const fetchPosts = async (slug) => {
                const res = await fetch(withLang(`/api/feed?limit=${limit}&compact=1${slug ? `&area=${encodeURIComponent(slug)}` : ''}`), { headers: { Accept: 'application/json' } });
                return res.json();
            };
            const load = async () => {
                loaded = true;
                const slug = area ? area() : null;
                try {
                    let data = slug ? await fetchPosts(slug) : null;
                    this.areaCount = slug ? data.count : null;
                    if (!data || !data.count) data = await fetchPosts(null);
                    this.html = data.html;
                    this.count = data.count;
                } catch {
                    this.count = 0;
                }
            };
            // The result page can change place (play again): reload for the new one.
            if (area) this.$watch(() => area(), () => loaded && load());
            if (!('IntersectionObserver' in window)) return load();
            const io = new IntersectionObserver(
                (entries) => {
                    if (!entries.some((e) => e.isIntersecting)) return;
                    io.disconnect();
                    load();
                },
                { rootMargin: '400px' },
            );
            io.observe(this.$el);
        },
    };
}
