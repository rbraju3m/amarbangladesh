import Alpine from 'alpinejs';
import { bnDigits } from './bn';
import { copyText, isMobile, shareLinks } from './share';
import { store, visitorId } from './store';
import { setTrackingContext, track } from './track';

/**
 * Community pages. The pages are server-rendered and cacheable; this component adds what depends on
 * the reader: their member identity (a token kept in localStorage, sent as a header), which posts are
 * theirs, what they marked helpful, and the write actions (post, answer, helpful, report, delete).
 */
const MEMBER = 'bd.member'; // { token, code, name }
const MARKS = 'bd.helpful'; // ['post:12', 'answer:40', …]

const PAGE_EVENTS = [
    [/^\/feed/, 'feed_view'],
    [/^\/p\//, 'post_view'],
    [/^\/ask/, 'ask_view'],
];

async function api(method, url, body = null, token = null) {
    const res = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-Member-Token': token } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
    if (!res.ok) {
        const message =
            res.status === 429
                ? 'একটু থামো, কিছুক্ষণ পরে আবার চেষ্টা করো।'
                : (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || 'কিছু একটা গোলমাল হয়েছে। আবার চেষ্টা করো।';
        throw Object.assign(new Error(message), { status: res.status });
    }
    return data;
}

function community() {
    return {
        member: store.get(MEMBER, null),
        marks: Object.fromEntries(store.get(MARKS, []).map((k) => [k, true])),
        counts: {}, // helpful counts changed on this page, by 'type:id'
        postId: null,
        accepted: null,
        answersCount: 0,
        sheet: null, // { kind: 'report' | 'delete', type, id }
        busy: false,
        formError: '',
        toast: '',
        bn: bnDigits,
        track,

        init() {
            setTrackingContext({ visitor_id: visitorId() });
            const event = PAGE_EVENTS.find(([re]) => re.test(location.pathname));
            if (event) track(event[1]);
        },

        get me() {
            return this.member?.code ?? null;
        },

        get myName() {
            return this.member?.name ?? '';
        },

        saveMember(member, token = this.member?.token) {
            this.member = { token, code: member.code, name: member.name };
            store.set(MEMBER, this.member);
        },

        // A member is created on the first write (a name may come with it). No sign-up step.
        async ensureMember(name = null) {
            if (!this.member?.token) {
                const data = await api('POST', '/api/members', { name });
                this.saveMember(data.member, data.token);
            }
            return this.member;
        },

        async call(method, url, body = null) {
            const { token } = await this.ensureMember();
            try {
                return await api(method, url, body, token);
            } catch (e) {
                if (e.status !== 401) throw e;
                // The stored identity is gone on the server: start a fresh one and retry once.
                this.member = null;
                store.set(MEMBER, null);
                return api(method, url, body, (await this.ensureMember(body?.name)).token);
            }
        },

        marked(key) {
            return !!this.marks[key];
        },

        count(key, initial) {
            const n = this.counts[key] ?? initial;
            return n ? bnDigits(n) : '';
        },

        async helpful(type, id) {
            const key = `${type}:${id}`;
            try {
                const data = await this.call('POST', '/api/helpful', { type, id });
                this.counts[key] = data.count;
                if (data.marked) this.marks[key] = true;
                else delete this.marks[key];
                store.set(MARKS, Object.keys(this.marks).slice(-1000));
            } catch (e) {
                this.flash(e.message);
            }
        },

        async report(reason) {
            const { type, id } = this.sheet;
            this.busy = true;
            try {
                await this.call('POST', '/api/reports', { type, id, reason });
                this.sheet = null;
                this.flash('ধন্যবাদ! আমরা দেখবো।');
            } catch (e) {
                this.flash(e.message);
            } finally {
                this.busy = false;
            }
        },

        async destroy() {
            const { type, id } = this.sheet;
            this.busy = true;
            try {
                await this.call('DELETE', type === 'post' ? `/api/posts/${id}` : `/api/answers/${id}`);
                this.sheet = null;
                if (type === 'post') {
                    location.href = '/feed';
                    return;
                }
                document.getElementById(`answer-${id}`)?.remove();
                this.answersCount = Math.max(0, this.answersCount - 1);
                if (this.accepted === id) this.accepted = null;
                this.flash('মুছে ফেলা হয়েছে');
            } catch (e) {
                this.flash(e.message);
            } finally {
                this.busy = false;
            }
        },

        async accept(answerId) {
            try {
                const data = await this.call('POST', `/api/posts/${this.postId}/accept`, { answer: this.accepted === answerId ? null : answerId });
                this.accepted = data.accepted;
                if (data.accepted) this.flash('সমাধান হিসেবে চিহ্নিত হলো ✓ উত্তরদাতাকে ধন্যবাদ!');
            } catch (e) {
                this.flash(e.message);
            }
        },

        async submitPost(form) {
            const payload = Object.fromEntries(new FormData(form));
            this.formError = '';
            this.busy = true;
            try {
                const data = await this.call('POST', '/api/posts', payload);
                this.saveMember(data.member);
                location.href = data.post.url;
            } catch (e) {
                this.formError = e.message;
                this.busy = false;
            }
        },

        async submitAnswer(postId, form) {
            const payload = Object.fromEntries(new FormData(form));
            this.formError = '';
            this.busy = true;
            try {
                const data = await this.call('POST', `/api/posts/${postId}/answers`, payload);
                this.saveMember(data.member);
                if (!document.getElementById(`answer-${data.answer.id}`)) {
                    document.getElementById('answers').insertAdjacentHTML('beforeend', data.html);
                }
                this.answersCount = data.answers_count;
                form.reset();
                form.querySelector('textarea').dispatchEvent(new Event('input'));
                this.$nextTick(() => document.getElementById(`answer-${data.answer.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                this.flash('তোমার উত্তর পোস্ট হয়েছে। ধন্যবাদ 🙏');
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.busy = false;
            }
        },

        // "More" is a real link (works without JS); with JS the next page's cards are appended in place.
        async loadMore(link, listSelector) {
            if (this.busy) return;
            const url = new URL(link.href);
            url.pathname = '/api/feed';
            this.busy = true;
            link.textContent = 'আনছি…';
            try {
                const data = await api('GET', url.pathname + url.search);
                document.querySelector(listSelector).insertAdjacentHTML('beforeend', data.html);
                if (data.next) link.href = data.next;
                else link.remove();
            } catch {
                this.flash('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করো।');
            } finally {
                link.textContent = 'আরও দেখো';
                this.busy = false;
            }
        },

        async sharePost(title, url) {
            track('post_shared', { meta: { channel: 'native' } });
            if (navigator.share && isMobile()) {
                try {
                    await navigator.share({ title, text: title, url });
                } catch {}
                return;
            }
            this.shareTo('copy', title, url);
        },

        async shareTo(channel, title, url) {
            track('post_shared', { meta: { channel } });
            if (channel === 'copy' || (channel === 'messenger' && !isMobile())) {
                const ok = await copyText(`${title}\n${url}`);
                this.flash(ok ? 'লিংক কপি হয়েছে! যাকে দরকার তাকে পাঠাও ✨' : 'কপি করা গেলো না।');
                return;
            }
            const href = shareLinks[channel](`${title} — জানা থাকলে উত্তর দাও 🙏`, url);
            if (channel === 'messenger') location.href = href;
            else window.open(href, '_blank', 'noopener');
        },

        async renameMe(name) {
            this.busy = true;
            try {
                const data = await this.call('PATCH', '/api/members/me', { name });
                this.saveMember(data.member);
                this.flash('নাম বদলানো হয়েছে');
                return true;
            } catch (e) {
                this.flash(e.message);
                return false;
            } finally {
                this.busy = false;
            }
        },

        async copyRecovery() {
            const ok = await copyText(`${location.origin}/me#t=${this.member.token}`);
            this.flash(ok ? 'গোপন লিংক কপি হয়েছে। নিজের কাছে রেখো, কাউকে দিও না 🔑' : 'কপি করা গেলো না।');
        },

        // /me: restore an identity from a private link (the token stays in the #fragment, never sent
        // to the server in a URL), then go to the member page.
        async openMe() {
            const match = location.hash.match(/^#t=([A-Za-z0-9]{40})$/);
            if (match) {
                history.replaceState(null, '', location.pathname);
                try {
                    const data = await api('GET', '/api/members/me', null, match[1]);
                    this.saveMember(data.member, match[1]);
                } catch {
                    this.flash('লিংকটা কাজ করছে না।');
                    return;
                }
            }
            if (this.me) location.replace(`/u/${this.me}`);
        },

        flash(message) {
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = ''), 3500);
        },
    };
}

Alpine.data('community', community);
window.Alpine = Alpine;
Alpine.start();
