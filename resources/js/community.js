import Alpine from 'alpinejs';
import { lang, loadDictionary, localeHeaders, num, path, t, withLang } from './i18n';
import { copyText, isMobile, shareLinks } from './share';
import { store, visitorId } from './store';
import { setTrackingContext, track } from './track';

/**
 * Community pages. The pages are server-rendered and cacheable; this component adds what depends on
 * the reader: who they are signed in as (a token kept in localStorage, sent as a header), which posts
 * are theirs, what they marked helpful, and the write actions (post, answer, helpful, report, delete).
 * Every write needs a signed-in account: a signed-out reader gets the sign-in sheet, and the action
 * carries on once they are in.
 */
const MEMBER = 'bd.member'; // { token, code, name, account }
const RETURN = 'bd.return'; // where to come back to after Google/Facebook
const DRAFT = 'bd.draft'; // { path, fields } typed text kept across a Google/Facebook round trip
const MARKS = 'bd.helpful'; // ['post:12', 'answer:40', …]

// Rejection used when the reader closes the sign-in sheet: actions stop quietly.
const CANCELLED = Object.assign(new Error(''), { cancelled: true });

const PAGE_EVENTS = [
    [/^\/feed/, 'feed_view'],
    [/^\/p\//, 'post_view'],
    [/^\/ask/, 'ask_view'],
];

async function api(method, url, body = null, token = null) {
    const res = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...localeHeaders(), ...(token ? { 'X-Member-Token': token } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
    if (!res.ok) {
        const message =
            res.status === 429
                ? t('একটু থামুন, কিছুক্ষণ পরে আবার চেষ্টা করুন।')
                : (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || t('কিছু একটা গোলমাল হয়েছে। আবার চেষ্টা করুন।');
        throw Object.assign(new Error(message), { status: res.status });
    }
    return data;
}

function community() {
    return {
        member: store.get(MEMBER, null),
        marks: Object.fromEntries(store.get(MARKS, []).map((k) => [k, true])),
        counts: {}, // helpful counts changed on this page, by 'type:id'
        owned: {}, // 'type:id' → true for the reader's own items on this page (from /api/mine)
        postId: null,
        accepted: null,
        answersCount: 0,
        sheet: null, // { kind: 'report' | 'delete', type, id }
        loginOpen: false,
        loginStep: 'choose', // choose | phone | code | email-login | email-register | email-forgot | forgot-sent
        loginPhone: '',
        loginEmail: '',
        authError: '',
        authBusy: false, // the sign-in sheet's own busy flag: the action that opened it is still "busy" 
        _waiting: [], // actions waiting for sign-in
        busy: false,
        formError: '',
        toast: '',
        bn: num,
        track,

        init() {
            this.restoreDraft();
            setTrackingContext({ visitor_id: visitorId() });
            const event = PAGE_EVENTS.find(([re]) => re.test(location.pathname));
            if (event) track(event[1]);
            this.refreshMine();
        },

        get me() {
            return this.member?.code ?? null;
        },

        get myName() {
            return this.member?.name ?? '';
        },

        get signedIn() {
            return !!(this.member?.token && this.member.account);
        },

        saveMember(member, token = this.member?.token) {
            this.member = { token, code: member.code, name: member.name, account: member.account };
            store.set(MEMBER, this.member);
        },

        // ---------- sign-in ----------

        /** Opens the sign-in sheet; resolves once signed in, rejects (quietly) if the sheet is closed. */
        openLogin() {
            this.loginStep = 'choose';
            this.formError = '';
            this.loginOpen = true;
            this.$nextTick(() => document.querySelector('.login-btn')?.focus()); // keyboard and screen readers land in the sheet
            return new Promise((resolve, reject) => this._waiting.push({ resolve, reject }));
        },

        closeLogin() {
            this.loginOpen = false;
            this._waiting.splice(0).forEach((w) => w.reject(CANCELLED));
        },

        async signedInWith(data) {
            const legacy = this.member?.token && !this.member.account ? this.member.token : null;
            this.saveMember(data.member, data.token);
            if (legacy) {
                // Posts written before sign-in existed move into the account.
                try {
                    this.saveMember((await api('POST', '/api/auth/link', { legacy_token: legacy }, data.token)).member);
                } catch {}
            }
            this.loginOpen = false;
            this.flash(t('লগইন হয়েছে। স্বাগতম!'));
            await this.refreshMine();
            this._waiting.splice(0).forEach((w) => w.resolve());
        },

        async authStep(work) {
            this.formError = '';
            this.authBusy = true;
            try {
                await work();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.authBusy = false;
            }
        },

        sendCode(form) {
            return this.authStep(async () => {
                await api('POST', '/api/auth/phone/send', { phone: form.phone.value });
                this.loginStep = 'code';
                this.$nextTick(() => document.getElementById('login-code')?.focus());
            });
        },

        verifyCode(form) {
            return this.authStep(async () => {
                await this.signedInWith(await api('POST', '/api/auth/phone/verify', { phone: this.loginPhone, code: form.code.value, name: form.name.value || null }));
                form.reset();
            });
        },

        emailAuth(form) {
            const register = this.loginStep === 'email-register';
            const payload = Object.fromEntries(new FormData(form));
            return this.authStep(async () => {
                await this.signedInWith(await api('POST', register ? '/api/auth/email/register' : '/api/auth/email/login', payload));
                form.password.value = '';
            });
        },

        forgotPassword(form) {
            return this.authStep(async () => {
                await api('POST', '/api/auth/email/forgot', { email: form.email.value });
                this.loginStep = 'forgot-sent';
            });
        },

        // Google/Facebook leave the page: remember where to come back to and what was typed.
        rememberReturn() {
            try {
                sessionStorage.setItem(RETURN, location.pathname + location.search);
                const fields = {};
                document.querySelectorAll('form[data-draft] [name]').forEach((el) => {
                    if (el.type !== 'password' && el.value) fields[el.name] = el.value;
                });
                sessionStorage.setItem(DRAFT, JSON.stringify({ path: location.pathname, fields }));
            } catch {}
        },

        restoreDraft() {
            try {
                const draft = JSON.parse(sessionStorage.getItem(DRAFT));
                if (!draft || draft.path !== location.pathname) return;
                sessionStorage.removeItem(DRAFT);
                this.$nextTick(() => {
                    for (const [name, value] of Object.entries(draft.fields)) {
                        const el = document.querySelector(`form[data-draft] [name="${name}"]`);
                        if (!el) continue;
                        el.value = value;
                        el.dispatchEvent(new Event('input'));
                    }
                });
            } catch {}
        },

        // /auth/done: Google/Facebook hand over the token in the #fragment (never sent to a server).
        async finishSocialLogin() {
            const params = new URLSearchParams(location.hash.slice(1));
            history.replaceState(null, '', location.pathname);
            const token = params.get('t');
            if (!token) {
                this.authError = params.get('error') === 'blocked' ? t('এই অ্যাকাউন্ট থেকে লেখা বন্ধ করা হয়েছে।') : t('লগইন হলো না। আবার চেষ্টা করুন।');
                return;
            }
            try {
                await this.signedInWith({ token, member: (await api('GET', '/api/members/me', null, token)).member });
            } catch {
                this.authError = t('লগইন হলো না। আবার চেষ্টা করুন।');
                return;
            }
            let back = path('/feed');
            try {
                back = sessionStorage.getItem(RETURN) || back;
                sessionStorage.removeItem(RETURN);
            } catch {}
            location.replace(back);
        },

        // /reset-password#t=…
        resetPassword(form) {
            const token = new URLSearchParams(location.hash.slice(1)).get('t');
            return this.authStep(async () => {
                await this.signedInWith(await api('POST', '/api/auth/email/reset', { token, password: form.password.value }));
                history.replaceState(null, '', location.pathname);
                location.replace(path(this.member.code ? `/u/${this.member.code}` : '/feed'));
            });
        },

        async logout() {
            try {
                await api('POST', '/api/auth/logout', null, this.member?.token);
            } catch {}
            this.member = null;
            this.owned = {};
            store.set(MEMBER, null);
            location.href = path('/feed');
        },

        /** A write: signs in first if needed, and once more if the server says the session is gone. */
        async call(method, url, body = null) {
            if (!this.signedIn) await this.openLogin();
            try {
                return await api(method, url, body, this.member.token);
            } catch (e) {
                if (e.status !== 401) throw e;
                this.member = this.member?.account ? null : this.member; // keep a legacy token so it can be linked
                store.set(MEMBER, this.member);
                await this.openLogin();
                return api(method, url, body, this.member.token);
            }
        },

        // Interface text: Bangla is the key; English pages translate it (resources/js/i18n.js).
        t,

        mine(type, id) {
            return !!this.owned[`${type}:${id}`];
        },

        // Owner controls: ask the server which of the items on the page are the reader's.
        async refreshMine() {
            const items = [...document.querySelectorAll('[data-own]')].map((el) => el.dataset.own);
            if (!this.member?.token || !items.length) return;
            try {
                const data = await api('POST', '/api/mine', { items: items.slice(0, 200) }, this.member.token);
                this.owned = Object.fromEntries(data.mine.map((k) => [k, true]));
            } catch {}
        },

        marked(key) {
            return !!this.marks[key];
        },

        count(key, initial) {
            const n = this.counts[key] ?? initial;
            return n ? num(n) : '';
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
                this.flash(t('ধন্যবাদ! আমরা দেখবো।'));
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
                    location.href = path('/feed');
                    return;
                }
                document.getElementById(`answer-${id}`)?.remove();
                this.answersCount = Math.max(0, this.answersCount - 1);
                if (this.accepted === id) this.accepted = null;
                this.flash(t('মুছে ফেলা হয়েছে'));
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
                if (data.accepted) this.flash(t('সমাধান হিসেবে চিহ্নিত হলো ✓ উত্তরদাতাকে ধন্যবাদ!'));
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
                this.owned[`answer:${data.answer.id}`] = true;
                if (!document.getElementById(`answer-${data.answer.id}`)) {
                    document.getElementById('answers').insertAdjacentHTML('beforeend', data.html);
                }
                this.answersCount = data.answers_count;
                form.reset();
                form.querySelectorAll('textarea, input[type=checkbox]').forEach((el) => el.dispatchEvent(new Event(el.type === 'checkbox' ? 'change' : 'input')));
                this.$nextTick(() => document.getElementById(`answer-${data.answer.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                this.flash(t('আপনার উত্তর পোস্ট হয়েছে। ধন্যবাদ 🙏'));
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
            url.pathname = '/api/feed'; // the next-page link points at /feed or /en/feed
            if (lang === 'en') url.searchParams.set('lang', 'en');
            this.busy = true;
            link.textContent = t('আনছি…');
            try {
                const data = await api('GET', url.pathname + url.search);
                document.querySelector(listSelector).insertAdjacentHTML('beforeend', data.html);
                if (data.next) link.href = data.next;
                else link.remove();
            } catch {
                this.flash(t('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।'));
            } finally {
                link.textContent = t('আরও দেখুন');
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
                this.flash(ok ? t('লিংক কপি হয়েছে! যাঁর দরকার তাঁকে পাঠান ✨') : t('কপি করা গেলো না।'));
                return;
            }
            const href = shareLinks[channel](t(':title — জানা থাকলে উত্তর দিন 🙏', { title }), url);
            if (channel === 'messenger') location.href = href;
            else window.open(href, '_blank', 'noopener');
        },

        async renameMe(name) {
            this.busy = true;
            try {
                const data = await this.call('PATCH', '/api/members/me', { name });
                this.saveMember(data.member);
                this.flash(t('নাম বদলানো হয়েছে'));
                return true;
            } catch (e) {
                this.flash(e.message);
                return false;
            } finally {
                this.busy = false;
            }
        },

        // /me: the profile of whoever is signed in, or the sign-in sheet.
        openMe() {
            if (this.signedIn) location.replace(path(`/u/${this.me}`));
        },

        flash(message) {
            if (!message) return; // a closed sign-in sheet: nothing to say
            this.toast = message;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => (this.toast = ''), 3500);
        },
    };
}

Alpine.data('community', community);
window.Alpine = Alpine;
loadDictionary().then(() => Alpine.start());
