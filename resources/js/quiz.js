import { bnDigits, possessive } from './bn';
import { canvasToBlob, renderCard } from './card';
import { confetti } from './confetti';
import { canShareFile, copyText, isInAppBrowser, isMobile, shareLinks } from './share';
import { setTrackingContext, track } from './track';

const store = {
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

const uuid = () =>
    crypto.randomUUID?.() ??
    '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16));

const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const wait = (ms) => new Promise((r) => setTimeout(r, reducedMotion() ? 0 : ms));

const BN_DIGITS = '০১২৩৪৫৬৭৮৯';

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
        revealSteps: REVEAL_STEPS,
        revealStep: -1,
        scanSlug: null,
        lockedSlug: null,
        revealDone: false,

        // share sheet
        sheetOpen: false,
        nameInput: '',
        savingName: false,
        cardUrl: null,
        cardBlob: null,
        cardBusy: false,
        toast: '',
        inApp: isInAppBrowser(),

        bn: bnDigits,
        possessive,

        init() {
            const visitorId = store.get('bd.visitor') || uuid();
            store.set('bd.visitor', visitorId);
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

            addEventListener('popstate', () => {
                if (location.pathname === '/' && this.screen === 'result') this.screen = 'landing';
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

        async choose(option) {
            if (this.picked) return;
            this.picked = option.id;
            this.answers[this.question.id] = option.id;
            track('question_answered', { meta: { q: this.qIndex + 1 } });
            await wait(420);
            this.picked = null;
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
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ answers: Object.values(this.answers), visitor_id: this.visitorId, ref: this.ref }),
            }).then(async (res) => {
                if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'সার্ভারে সমস্যা হয়েছে।');
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
                this.error = e.message || 'ইন্টারনেট সংযোগ দেখো, তারপর আবার চেষ্টা করো।';
            }
        },

        showResult(result, token, fresh) {
            this.result = result;
            this.ownerToken = token;
            this.nameInput = result.name || '';
            this.cardUrl = null;
            this.screen = 'result';
            if (fresh) {
                const mine = store.get('bd.mine', {});
                mine[result.code] = token;
                store.set('bd.mine', mine);
                history.pushState({}, '', `/r/${result.code}`);
                navigator.vibrate?.(30);
            }
            this.animateResult(result, fresh);
            document.title = `${result.location.emoji} ${result.location.name_bn} — তোমার বাংলাদেশ কোথায়?`;
            track('result_viewed', { result: result.code, meta: { fresh: fresh ? 1 : 0 } });
            window.scrollTo(0, 0);
        },

        // Count the match % up, grow the trait bars and, for a fresh result, burst confetti.
        animateResult(result, fresh) {
            this.barsIn = false;
            this.shownPct = 0;
            const target = result.match_pct;
            requestAnimationFrame(() =>
                requestAnimationFrame(() => {
                    this.barsIn = true;
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
            history.pushState({}, '', '/');
            this.start();
        },

        // ---------- sharing ----------

        get shareText() {
            const r = this.result;
            return `আমার বাংলাদেশ হলো ${r.location.name_bn} ${r.location.emoji} — ভাইব ম্যাচ ${bnDigits(r.match_pct)}%! তোমার বাংলাদেশ কোথায়? 👉`;
        },

        async openSheet() {
            this.sheetOpen = true;
            track('share_clicked', { result: this.result.code, meta: { channel: 'sheet' } });
            if (!this.cardUrl) await this.buildCard();
        },

        async buildCard() {
            this.cardBusy = true;
            try {
                const canvas = await renderCard(this.result);
                this.cardBlob = await canvasToBlob(canvas);
                if (this.cardUrl) URL.revokeObjectURL(this.cardUrl);
                this.cardUrl = URL.createObjectURL(this.cardBlob);
            } finally {
                this.cardBusy = false;
            }
        },

        async saveName() {
            const name = this.nameInput.trim();
            if ((this.result.name || '') === name) return;
            this.savingName = true;
            try {
                const res = await fetch(`/api/results/${this.result.code}/name`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ owner_token: this.ownerToken, name }),
                });
                if (res.ok) {
                    this.result = (await res.json()).result;
                    this.nameInput = this.result.name || '';
                    if (name) track('name_added', { result: this.result.code });
                    await this.buildCard();
                } else {
                    this.flash('নামটা সেভ করা গেলো না।');
                }
            } finally {
                this.savingName = false;
            }
        },

        cardFile() {
            return new File([this.cardBlob], `amar-bangladesh-${this.result.location.slug}.png`, { type: 'image/png' });
        },

        get canNativeShare() {
            return !this.inApp && !!this.cardBlob && canShareFile(this.cardFile());
        },

        async shareNative() {
            track('share_clicked', { result: this.result.code, meta: { channel: 'native' } });
            try {
                await navigator.share({ files: [this.cardFile()], text: `${this.shareText} ${this.result.url}` });
            } catch {}
        },

        shareTo(channel) {
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
            const ok = await copyText(`${this.shareText} ${this.result.url}`);
            track('link_copied', { result: this.result.code });
            this.flash(ok ? 'লিংক কপি হয়েছে! এখন যেকোনো জায়গায় পেস্ট করো ✨' : 'কপি করা গেলো না।');
        },

        saveCard() {
            track('card_saved', { result: this.result.code, meta: { inapp: this.inApp ? 1 : 0 } });
            if (this.inApp) {
                this.flash('ছবিটার ওপর চেপে ধরে রাখো, তারপর "Save image" চাপো');
                return;
            }
            const a = Object.assign(document.createElement('a'), { href: this.cardUrl, download: this.cardFile().name });
            a.click();
        },

        flash(message) {
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = ''), 3200);
        },
    };
}
