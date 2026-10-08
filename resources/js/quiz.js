import { localeHeaders, num, path, possessive, t } from './i18n';
import { canvasToBlob, FORMATS, formatKeys, renderCard, TEMPLATES, templateKeys, THEMES, themeKeys } from './card';
import { confetti } from './confetti';
import { canShareFile, copyText, isInAppBrowser, isMobile, shareLinks } from './share';
import { store, visitorId as getVisitorId } from './store';
import { setTrackingContext, track } from './track';

const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((r) => setTimeout(r, reducedMotion() ? 0 : ms));

const BN_DIGITS = '০১২৩৪৫৬৭৮৯';

// Translated when used (the dictionary loads after this module runs).
const REVEAL_STEPS = ['ঘোরাঘুরির ভাইব', 'খাবারের রুচি', 'আড্ডার এনার্জি', 'তোমার বাংলাদেশ'];

export default function quiz(boot) {
    return {
        questions: boot.questions,
        locations: boot.locations,
        screen: 'landing',
        qIndex: 0,
        answers: {},
        picked: null,
        result: null,
        shared: boot.shared ?? null,
        ref: null,
        ownerToken: null,
        error: null,

        // landing: place focused by hovering/tapping a map dot or card
        focusSlug: null,

        // result entrance animation
        shownPct: 0,
        barsIn: false,

        // reveal
        placeSlug: null, // place open in the explore sheet
        openTrait: null, // trait row expanded to show the answers behind it
        dir: 'fwd', // which way the next question slides in
        hoverOpt: null, // answer under the pointer/focus, lit up in the question's emoji picture
        swipeX: null,
        revealSteps: REVEAL_STEPS.map((step) => t(step)),
        revealStep: -1,
        scanSlug: null,
        lockedSlug: null,
        revealDone: false,

        // share sheet
        sheetOpen: false,
        nameInput: '',
        nameEditing: false,
        savingName: false,
        cardName: null,
        cardKey: null,
        cardTemplate: templateKeys.includes(store.get('bd.card')) ? store.get('bd.card') : templateKeys[0],
        cardTemplates: TEMPLATES.map(({ key, label, icon }) => ({ key, label, icon })),
        cardTheme: themeKeys.includes(store.get('bd.theme')) ? store.get('bd.theme') : themeKeys[0],
        cardThemes: THEMES.map(({ key, label, bg, accent }) => ({ key, label, bg, accent })),
        cardFormat: formatKeys.includes(store.get('bd.format')) ? store.get('bd.format') : formatKeys[0],
        cardFormats: FORMATS.map(({ key, label }) => ({ key, label })),
        thumbs: {}, // `${template}|${theme}|${format}|${name}` → small JPEG data URL for the picker previews
        cardUrl: null,
        cardBlob: null,
        cardBusy: false,
        toast: '',
        inApp: isInAppBrowser(),

        bn: num,
        possessive,
        t,

        init() {
            this.optionById = Object.fromEntries(this.questions.flatMap((q) => q.options.map((o) => [o.id, o])));
            const visitorId = getVisitorId();
            setTrackingContext({ visitor_id: visitorId });
            this.visitorId = visitorId;

            if (this.shared) {
                const token = store.get('bd.mine', {})[this.shared.code];
                if (token) {
                    this.showResult(this.shared, token, false);
                } else {
                    this.ref = this.shared.code;
                    setTrackingContext({ ref: this.ref });
                    this.screen = 'teaser';
                    track('share_page_view', { result: this.ref });
                }
            } else {
                track('landing_view');
            }

            // Re-draw the share card shortly after typing stops, so the preview shows the name live.
            let redraw;
            this.$watch('nameInput', () => {
                clearTimeout(redraw);
                // The result page shows the card too, so keep it current whenever one has been drawn.
                if (this.sheetOpen || this.cardUrl) redraw = setTimeout(() => this.buildCard(), 350);
            });

            addEventListener('popstate', () => {
                if (location.pathname === path('/') && this.screen === 'result') this.screen = 'landing';
            });
        },

        get question() {
            return this.questions[this.qIndex];
        },

        get progress() {
            return ((this.qIndex + (this.picked ? 1 : 0)) / this.questions.length) * 100;
        },

        get focusPlace() {
            return this.locations.find((l) => l.slug === this.focusSlug) ?? null;
        },

        get liveName() {
            return this.nameInput.trim();
        },

        get accent() {
            return (this.result ?? this.shared)?.location.accent ?? '#2f7d4f';
        },

        start() {
            this.answers = {};
            this.qIndex = 0;
            this.picked = null;
            this.error = null;
            this.screen = 'question';
            track('quiz_started', { meta: { referred: this.ref ? 1 : 0 } });
            window.scrollTo(0, 0);
            // Warm the illustration cache for the visual question and the result.
            this.locations.forEach((l) => (new Image().src = l.illustration));
        },

        // A short line of encouragement at the halfway point and near the end.
        get nudge() {
            const left = this.questions.length - this.qIndex;
            if (left === 1) return t('শেষ প্রশ্ন! 🎉');
            if (left === 2) return t('আর মাত্র ২টা! 💪');
            if (this.qIndex === Math.floor(this.questions.length / 2)) return t('অর্ধেক শেষ! 🔥');
            return null;
        },

        // Ink ripple from the tap point on an answer card.
        ripple(e) {
            const el = e.currentTarget;
            const box = el.getBoundingClientRect();
            el.style.setProperty('--rx', `${e.clientX - box.left}px`);
            el.style.setProperty('--ry', `${e.clientY - box.top}px`);
            el.classList.remove('is-rippling');
            void el.offsetWidth; // restart the animation
            el.classList.add('is-rippling');
        },

        // Swipe right on a question to go back, like a phone's back gesture.
        swipeStart(e) {
            const t = e.changedTouches[0];
            this.swipeX = { x: t.clientX, y: t.clientY };
        },

        swipeEnd(e) {
            if (!this.swipeX || this.picked) return;
            const t = e.changedTouches[0];
            const dx = t.clientX - this.swipeX.x, dy = Math.abs(t.clientY - this.swipeX.y);
            this.swipeX = null;
            if (dx > 70 && dy < 50) this.back();
        },

        async choose(option) {
            if (this.picked) return;
            this.picked = option.id;
            this.dir = 'fwd';
            navigator.vibrate?.(12);
            this.answers[this.question.id] = option.id;
            track('question_answered', { meta: { q: this.qIndex + 1 } });
            await wait(420);
            this.picked = null;
            this.hoverOpt = null;
            if (this.qIndex < this.questions.length - 1) {
                this.qIndex++;
            } else {
                this.submit();
            }
        },

        // Desktop shortcuts: 1–4 (or ১–৪) pick an answer, ← goes back.
        onKey(e) {
            if (e.ctrlKey || e.metaKey || e.altKey || this.picked) return;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                return this.back();
            }
            const n = BN_DIGITS.includes(e.key) ? BN_DIGITS.indexOf(e.key) : Number.parseInt(e.key, 10);
            const option = this.question?.options[n - 1];
            if (option) {
                e.preventDefault();
                this.choose(option);
            }
        },

        back() {
            this.dir = 'back';
            this.hoverOpt = null;
            if (this.qIndex === 0) {
                this.screen = this.ref ? 'teaser' : 'landing';
                return;
            }
            this.qIndex--;
        },

        async submit() {
            this.screen = 'reveal';
            this.error = null;
            this.revealStep = -1;
            this.lockedSlug = null;
            this.revealDone = false;

            const request = fetch('/api/results', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...localeHeaders() },
                body: JSON.stringify({ answers: Object.values(this.answers), visitor_id: this.visitorId, ref: this.ref }),
            }).then(async (res) => {
                if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || t('সার্ভারে সমস্যা হয়েছে।'));
                return res.json();
            });

            // Scan across the map while captions tick, in parallel with the request.
            const scan = (async () => {
                let i = 0;
                const timer = setInterval(() => (this.scanSlug = this.locations[i++ % this.locations.length].slug), 110);
                for (let s = 0; s < REVEAL_STEPS.length; s++) {
                    this.revealStep = s;
                    await wait(260);
                }
                return timer;
            })();

            try {
                const [data, timer] = await Promise.all([request, scan]);
                clearInterval(timer);
                this.scanSlug = null;
                new Image().src = data.result.location.illustration;
                this.lockedSlug = data.result.location.slug;
                await wait(650);
                this.showResult(data.result, data.owner_token, true);
            } catch (e) {
                clearInterval(await scan);
                this.scanSlug = null;
                this.error = e.message || t('ইন্টারনেট সংযোগ দেখো, তারপর আবার চেষ্টা করো।');
            }
        },

        showResult(result, token, fresh) {
            this.result = result;
            this.ownerToken = token;
            this.nameInput = result.name || '';
            this.nameEditing = false;
            this.cardUrl = null;
            this.cardName = null;
            this.cardKey = null;
            this.thumbs = {};
            this.screen = 'result';
            if (fresh) {
                const mine = store.get('bd.mine', {});
                mine[result.code] = token;
                store.set('bd.mine', mine);
                history.pushState({}, '', path(`/r/${result.code}`));
                navigator.vibrate?.(30);
            }
            this.animateResult(result, fresh);
            // Draw the share card right away: the result page previews it before the sheet is opened.
            this.$nextTick(() => this.buildCard());
            document.title = t(':place — তোমার বাংলাদেশ কোথায়?', { place: `${result.location.emoji} ${result.location.name}` });
            track('result_viewed', { result: result.code, meta: { fresh: fresh ? 1 : 0 } });
            window.scrollTo(0, 0);
        },

        // Grow the trait bars once they scroll into view (on phones they start below the fold).
        observeBars(el) {
            if (!('IntersectionObserver' in window)) return (this.barsIn = true);
            const io = new IntersectionObserver(
                (entries) => {
                    if (!entries.some((e) => e.isIntersecting)) return;
                    this.barsIn = true;
                    io.disconnect();
                },
                { threshold: 0.35 },
            );
            io.observe(el);
        },

        toggleTrait(key) {
            this.openTrait = this.openTrait === key ? null : key;
        },

        traitAnswers(t) {
            return (t.answers ?? []).map((id) => this.optionById[id]).filter(Boolean);
        },

        // The explore sheet: one place, how well it matches this player and which answers pulled toward it.
        openPlace(slug) {
            this.placeSlug = slug;
            track('place_opened', { result: this.result?.code, meta: { place: slug } });
        },

        get places() {
            const bySlug = Object.fromEntries(this.locations.map((l) => [l.slug, l]));
            return (this.result?.places ?? []).map((p, i) => ({ ...bySlug[p.slug], ...p, rank: i + 1 })).filter((p) => p.name);
        },

        // On the result page the sheet shows this player's match; elsewhere (landing) it's a plain preview.
        get placeList() {
            return this.screen === 'result' && this.places.length ? this.places : this.locations;
        },

        get placeView() {
            if (!this.placeSlug) return null;
            const p = this.placeList.find((x) => x.slug === this.placeSlug);
            if (!p) return null;
            if (!p.answers) return { ...p, preview: true, options: [], isTop: false };
            return { ...p, options: p.answers.map((id) => this.optionById[id]).filter(Boolean), isTop: p.rank === 1 };
        },

        // Map dots: the first tap shows the tooltip, a second tap on the same dot opens the sheet.
        tapDot(slug) {
            if (this.focusSlug === slug) return this.openPlace(slug);
            this.focusSlug = slug;
        },

        stepPlace(delta) {
            const list = this.placeList;
            const i = list.findIndex((p) => p.slug === this.placeSlug);
            if (i < 0) return;
            this.placeSlug = list[(i + delta + list.length) % list.length].slug;
        },

        // Count the match % up, grow the trait bars and, for a fresh result, burst confetti.
        animateResult(result, fresh) {
            this.barsIn = false;
            this.openTrait = null;
            this.placeSlug = null;
            this.shownPct = 0;
            const target = result.match_pct;
            requestAnimationFrame(() =>
                requestAnimationFrame(() => {
                    if (reducedMotion()) {
                        this.shownPct = target;
                        return;
                    }
                    const start = performance.now();
                    const tick = (now) => {
                        const t = Math.min(1, (now - start) / 1100);
                        this.shownPct = Math.round(target * (1 - (1 - t) ** 3));
                        if (t < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                }),
            );
            if (fresh) setTimeout(() => confetti([result.location.accent, '#e03a3e', '#006a4e', '#f4b400', '#ffffff']), 250);
        },

        retake() {
            track('retake_clicked', { result: this.result?.code });
            history.pushState({}, '', path('/'));
            this.start();
        },

        // ---------- sharing ----------

        get shareText() {
            const r = this.result;
            return t('আমার বাংলাদেশ হলো :place — ভাইব ম্যাচ :n%! তোমার বাংলাদেশ কোথায়? 👉', { place: `${r.location.name} ${r.location.emoji}`, n: num(r.match_pct) });
        },

        async openSheet() {
            this.sheetOpen = true;
            track('share_clicked', { result: this.result.code, meta: { channel: 'sheet' } });
            // Invite a name: focus the empty field on desktop (on phones it would pop the keyboard over the card).
            if (!this.liveName && matchMedia('(hover: hover)').matches) this.$nextTick(() => this.$refs.sheetName?.focus());
            if (!this.cardUrl || this.cardName !== this.liveName) await this.buildCard();
            else this.buildThumbs(this.cardKey); // finish any previews cut short when the sheet closed
        },

        async pickTemplate(key) {
            if (key === this.cardTemplate) return;
            this.cardTemplate = key;
            store.set('bd.card', key);
            await this.buildCard();
        },

        async pickTheme(key) {
            if (key === this.cardTheme) return;
            this.cardTheme = key;
            store.set('bd.theme', key);
            await this.buildCard();
        },

        async pickFormat(key) {
            if (key === this.cardFormat) return;
            this.cardFormat = key;
            store.set('bd.format', key);
            await this.buildCard();
        },

        get cardSquare() {
            return this.cardFormat === 'square';
        },

        thumb(template, theme) {
            return this.thumbs[`${template}|${theme}|${this.cardFormat}|${this.cardName ?? ''}`] ?? null;
        },

        async buildCard() {
            this.cardBusy = true;
            const name = this.liveName;
            const { cardTemplate: template, cardTheme: theme, cardFormat: format } = this;
            const key = `${template}|${theme}|${format}|${name}`;
            this.cardKey = key;
            try {
                const canvas = await renderCard({ ...this.result, name: name || null }, { template, theme, format });
                if (this.cardKey !== key) return; // typing or the design moved on; a newer render is under way
                if (this.cardName !== name) this.thumbs = {};
                this.cardName = name;
                this.cardBlob = await canvasToBlob(canvas);
                if (this.cardUrl) URL.revokeObjectURL(this.cardUrl);
                this.cardUrl = URL.createObjectURL(this.cardBlob);
            } finally {
                if (this.cardKey === key) this.cardBusy = false;
            }
            if (this.cardKey === key) this.buildThumbs(key);
        },

        // Colour swatch for the theme picker: the theme's background with its accent as a dot
        // ("place" uses the result's own colour on paper).
        swatch(t) {
            const accent = t.accent ?? this.result?.location.accent;
            const bg = t.bg ?? ['#fbf8f1', '#fbf8f1'];
            return `background: radial-gradient(circle at 50% 50%, ${accent} 0 34%, transparent 35%), linear-gradient(160deg, ${bg[0]}, ${bg[1]})`;
        },

        // Small previews for the design picker: every design in the current colour. Drawn one per frame
        // after the main card, so the big preview is never held up.
        async buildThumbs(key) {
            const name = this.cardName;
            const format = this.cardFormat;
            const jobs = templateKeys.map((t) => [t, this.cardTheme]).filter(([t, th]) => !this.thumb(t, th));
            for (const [template, theme] of jobs) {
                await new Promise(requestAnimationFrame);
                if (this.cardKey !== key || !this.sheetOpen) return;
                const k = `${template}|${theme}|${format}|${name}`;
                if (this.thumbs[k]) continue;
                const canvas = await renderCard({ ...this.result, name: name || null }, { template, theme, format, scale: 0.2 });
                if (this.cardName === name) this.thumbs = { ...this.thumbs, [k]: canvas.toDataURL('image/jpeg', 0.85) };
            }
        },

        async saveName() {
            const name = this.nameInput.trim();
            if ((this.result.name || '') === name) return;
            this.savingName = true;
            try {
                const res = await fetch(`/api/results/${this.result.code}/name`, {
                    method: 'PATCH',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ owner_token: this.ownerToken, name }),
                });
                if (res.ok) {
                    this.result = (await res.json()).result;
                    this.nameInput = this.result.name || '';
                    if (name) track('name_added', { result: this.result.code });
                    if (this.cardUrl && this.cardName !== this.liveName) await this.buildCard();
                } else {
                    this.flash(t('নামটা সেভ করা গেলো না।'));
                }
            } finally {
                this.savingName = false;
            }
        },

        async submitName() {
            await this.saveName();
            this.nameEditing = false;
        },

        // A typed but unsaved name is saved whenever the player shares, without delaying the share
        // itself (popup and share APIs must run straight from the tap).
        persistName() {
            if (!this.savingName && this.liveName !== (this.result.name || '')) this.saveName();
        },

        cardFile() {
            return new File([this.cardBlob], `amar-bangladesh-${this.result.location.slug}-${this.cardTemplate}-${this.cardTheme}-${this.cardFormat}.png`, { type: 'image/png' });
        },

        get canNativeShare() {
            return !this.inApp && !!this.cardBlob && canShareFile(this.cardFile());
        },

        async shareNative() {
            this.persistName();
            track('share_clicked', { result: this.result.code, meta: { channel: 'native', template: this.cardTemplate, theme: this.cardTheme, format: this.cardFormat } });
            try {
                await navigator.share({ files: [this.cardFile()], text: `${this.shareText} ${this.result.url}` });
            } catch {}
        },

        shareTo(channel) {
            this.persistName();
            track('share_clicked', { result: this.result.code, meta: { channel } });
            if (channel === 'messenger' && !isMobile()) {
                return this.copyLink();
            }
            const href = shareLinks[channel](this.shareText, this.result.url);
            if (channel === 'messenger') {
                location.href = href;
            } else {
                window.open(href, '_blank', 'noopener');
            }
        },

        async copyLink() {
            this.persistName();
            const ok = await copyText(`${this.shareText} ${this.result.url}`);
            track('link_copied', { result: this.result.code });
            this.flash(ok ? t('লিংক কপি হয়েছে! এখন যেকোনো জায়গায় পেস্ট করো ✨') : t('কপি করা গেলো না।'));
        },

        saveCard() {
            this.persistName();
            track('card_saved', { result: this.result.code, meta: { inapp: this.inApp ? 1 : 0, template: this.cardTemplate, theme: this.cardTheme, format: this.cardFormat } });
            if (this.inApp) {
                this.flash(t('ছবিটার ওপর চেপে ধরে রাখো, তারপর "Save image" চাপো'));
                return;
            }
            const a = Object.assign(document.createElement('a'), { href: this.cardUrl, download: this.cardFile().name });
            a.click();
        },

        // Links into the community from the quiz page; the event is flushed as the page unloads.
        trackCommunity(from, to) {
            track('community_clicked', { result: this.result?.code, meta: { from, to } });
        },

        flash(message) {
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = ''), 3200);
        },
    };
}
