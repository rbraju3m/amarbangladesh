/**
 * A few community posts embedded in the quiz page (landing and result). Fetched when the block
 * nears the viewport, so the quiz's first paint and cached HTML are untouched.
 */
export default function communityPreview(limit = 3) {
    return {
        html: '',
        count: null, // null = not loaded yet
        init() {
            const load = async () => {
                try {
                    const res = await fetch(`/api/feed?limit=${limit}&compact=1`, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    this.html = data.html;
                    this.count = data.count;
                } catch {
                    this.count = 0;
                }
            };
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
