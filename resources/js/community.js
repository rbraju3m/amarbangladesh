import Alpine from 'alpinejs';
import { lang, loadDictionary, localeHeaders, num, path, t, withLang } from './i18n';
import infinite from './infinite';
import modal from './modal';
import { photoPicker, send as sendPhoto } from './photos';
import { copyText, isInAppBrowser, isMobile, shareLinks } from './share';
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
const SAVED = 'bd.saved'; // [12, 40, …] saved post ids, so cards fetched later show their state at once

// Rejection used when the reader closes the sign-in sheet: actions stop quietly.
const CANCELLED = Object.assign(new Error(''), { cancelled: true });

const PAGE_EVENTS = [
    [/^\/?$/, 'home_view'],
    [/^\/feed/, 'feed_view'],
    [/^\/p\//, 'post_view'],
    [/^\/ask/, 'ask_view'],
    [/^\/notifications/, 'notifications_view'],
    [/^\/saved/, 'saved_view'],
];

/** The anonymous visitor id rides along to Google/Facebook so a new account's sign-up event can be tied to it. */
function withVisitor(href) {
    const url = new URL(href, location.href);
    url.searchParams.set('v', visitorId());
    return url.pathname + url.search;
}

async function api(method, url, body = null, token = null) {
    const res = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...localeHeaders(),
            ...(token ? { 'X-Member-Token': token } : {}),
            ...(method !== 'GET' ? { 'X-Visitor': visitorId() } : {}), // ties sign-ups and posts to the anonymous visitor (analytics)
        },
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

/**
 * A composer's fields as JSON. Forms with a photo picker also send `photos` (the uploaded ids, in
 * order, possibly none, so an edit can remove them all); posting waits until uploads have finished.
 */
function formPayload(form) {
    const data = new FormData(form);
    const payload = Object.fromEntries([...data].filter(([name]) => name !== 'photos[]'));
    const picker = form.querySelector('[data-photos]');
    if (picker) {
        if (picker.dataset.busy) throw new Error(t('ছবি আপলোড হচ্ছে, একটু অপেক্ষা করুন।'));
        payload.photos = data.getAll('photos[]').map(Number);
    }
    return payload;
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
        unread: 0, // unread notifications, for the dot on "আমি"
        notices: { state: 'loading', next: null, email: null, prefs: null }, // the /notifications page
        saved: Object.fromEntries(store.get(SAVED, []).map((id) => [id, true])), // post id → true
        savedList: { state: 'loading', next: null }, // the /saved page
        share: null, // { title, url, from } while the share sheet is open
        inApp: isInAppBrowser(),
        bn: num,
        track,

        init() {
            this.watchCardButtons();
            this.restoreDraft();
            setTrackingContext({ visitor_id: visitorId() });
            const event = PAGE_EVENTS.find(([re]) => re.test(location.pathname.replace(/^\/en(?=\/|$)/, '')));
            const viewed = Number(document.querySelector('[data-post-view]')?.dataset.postView) || null;
            if (event) track(event[1], viewed ? { meta: { post: viewed } } : {});
            if (viewed) this.countView(viewed);
            this.refreshMine();
            this.refreshUnread();
            const url = new URL(location.href);
            if (url.searchParams.has('deleted')) {
                url.searchParams.delete('deleted');
                history.replaceState(null, '', url);
                this.$nextTick(() => this.flash(t('আপনার অ্যাকাউন্ট মুছে ফেলা হয়েছে।')));
            }
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
            this.refreshUnread();
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
        rememberReturn(link = null) {
            if (link) link.href = withVisitor(link.href);
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
            store.set(SAVED, []); // private to the account
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

        // ---------- photos ----------

        viewer: null, // { photos: [{ src, width, height, alt }], index } while the full-screen viewer is open
        touchX: null,

        /** Opens the viewer on photo `index` of a grid (partials/photo-grid carries the list in data-gallery). */
        openPhotos(grid, index) {
            this.viewer = { photos: JSON.parse(grid.dataset.gallery), index };
        },

        stepPhoto(step) {
            if (!this.viewer || this.viewer.photos.length < 2) return;
            this.viewer.index = (this.viewer.index + step + this.viewer.photos.length) % this.viewer.photos.length;
        },

        /** One photo for a composer (resources/js/photos.js), signed in like any other write. */
        async uploadPhoto(blob, onProgress) {
            if (!this.signedIn) await this.openLogin();
            const headers = () => ({ Accept: 'application/json', ...localeHeaders(), 'X-Member-Token': this.member.token, 'X-Visitor': visitorId() });
            try {
                return await sendPhoto(blob, headers(), onProgress);
            } catch (e) {
                if (e.status !== 401) throw e;
                this.member = this.member?.account ? null : this.member;
                store.set(MEMBER, this.member);
                await this.openLogin();
                return sendPhoto(blob, headers(), onProgress);
            }
        },

        // Interface text: Bangla is the key; English pages translate it (resources/js/i18n.js).
        t,

        mine(type, id) {
            return !!this.owned[`${type}:${id}`];
        },

        // Owner controls: ask the server which of the items on the page are the reader's.
        async refreshMine() {
            const posts = [...document.querySelectorAll('[data-save]')].map((el) => `post:${el.dataset.save}`);
            const items = [...new Set([...document.querySelectorAll('[data-own]')].map((el) => el.dataset.own).concat(posts))];
            if (!this.member?.token || !items.length) return;
            try {
                const data = await api('POST', '/api/mine', { items: items.slice(0, 200) }, this.member.token);
                this.owned = Object.fromEntries(data.mine.map((k) => [k, true]));
                const saved = new Set(data.saved ?? []);
                items.filter((k) => k.startsWith('post:')).forEach((k) => this.rememberSaved(+k.slice(5), saved.has(k)));
                this.paintSaved();
            } catch {}
        },

        /** Reports a post view (the server counts it at most once a day per browser and never the author's). */
        countView(id) {
            const headers = { Accept: 'application/json', 'X-Visitor': visitorId() };
            if (this.member?.token) headers['X-Member-Token'] = this.member.token;
            fetch(`/api/posts/${id}/view`, { method: 'POST', keepalive: true, headers }).catch(() => {});
        },

        // ---------- saved posts ----------
        // The bookmark buttons are plain HTML (`[data-save]` in post cards and on the post page, also
        // fetched into lists later): one click listener here, and their state painted as a class.

        watchCardButtons() {
            document.documentElement.dataset.cardActions = '';
            document.addEventListener('click', (e) => {
                const share = e.target.closest('[data-share]');
                if (share) {
                    e.preventDefault();
                    return this.sharePost(share.dataset.title, share.dataset.share, 'card');
                }
                const button = e.target.closest('[data-save]');
                if (!button) return;
                e.preventDefault();
                this.toggleSave(+button.dataset.save);
            });
            new MutationObserver((changes) => changes.some((c) => [...c.addedNodes].some((n) => n.querySelector?.('[data-save]'))) && this.paintSaved()).observe(document.body, { childList: true, subtree: true });
            this.paintSaved();
        },

        paintSaved() {
            document.querySelectorAll('[data-save]').forEach((button) => {
                const on = !!this.saved[button.dataset.save];
                button.classList.toggle('is-on', on);
                button.setAttribute('aria-pressed', on);
                if (button.hasAttribute('aria-label')) {
                    const label = on ? t('সেভ থেকে সরান') : t('পরে পড়তে সেভ করুন');
                    button.setAttribute('aria-label', label);
                    button.title = label;
                }
            });
        },

        rememberSaved(id, on) {
            if (on) this.saved[id] = true;
            else delete this.saved[id];
            store.set(SAVED, Object.keys(this.saved).map(Number).slice(-1000));
        },

        async toggleSave(id) {
            const on = !this.saved[id];
            this.rememberSaved(id, on); // at once; put back if the server says no
            this.paintSaved();
            try {
                await this.call('POST', '/api/saved', { post: id, saved: on });
                track('post_saved', { meta: { saved: on ? 1 : 0, page: location.pathname.replace(/^\/en(?=\/|$)/, '').split('/')[1] || 'home' } });
                this.flash(on ? t('সেভ হয়েছে। আপনার পাতার “সেভ করা পোস্ট”-এ পাবেন।') : t('সেভ থেকে সরানো হয়েছে'));
            } catch (e) {
                this.rememberSaved(id, !on);
                this.paintSaved();
                if (!e.cancelled) this.flash(e.message);
            }
        },

        /** The /saved page: the member's saved posts, newest saved first. */
        async loadSaved(more = false) {
            if (!this.signedIn || this.busy) return;
            this.busy = true;
            try {
                const data = await this.call('GET', `/api/saved${more && this.savedList.next ? `?before=${this.savedList.next}` : ''}`);
                const list = document.getElementById('saved-list');
                if (more) list.insertAdjacentHTML('beforeend', data.html);
                else list.innerHTML = data.html;
                data.ids.forEach((id) => this.rememberSaved(id, true));
                this.paintSaved();
                this.savedList = { state: list.children.length ? 'ready' : 'empty', next: data.next };
            } catch (e) {
                if (!e.cancelled) this.savedList.state = 'error';
            } finally {
                this.busy = false;
            }
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
                // A top-level answer with replies leaves a placeholder (its replies keep their context);
                // without replies the whole thread goes; a reply just disappears.
                const el = document.getElementById(`answer-${id}`);
                const thread = document.getElementById(`thread-${id}`);
                if (thread) {
                    if (thread.querySelector('.replies')?.children.length) el?.replaceWith(Object.assign(document.createElement('p'), { className: 'answer-card text-sm text-ink-2', textContent: t('এই উত্তরটা আর নেই।') }));
                    else thread.remove();
                    this.answersCount = Math.max(0, this.answersCount - 1);
                } else {
                    el?.remove();
                }
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

        /** A reply in a thread (`thread` is that thread's Alpine data: replyTo, more, after). */
        async submitReply(postId, form, thread) {
            const payload = Object.fromEntries(new FormData(form));
            this.busy = true;
            try {
                const data = await this.call('POST', `/api/posts/${postId}/answers`, payload);
                this.saveMember(data.member);
                this.owned[`answer:${data.answer.id}`] = true;
                if (!document.getElementById(`answer-${data.answer.id}`)) {
                    document.getElementById(`replies-${data.answer.thread}`)?.insertAdjacentHTML('beforeend', data.html);
                }
                thread.replyTo = null;
                this.$nextTick(() => document.getElementById(`answer-${data.answer.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                this.flash(t('আপনার জবাব পোস্ট হয়েছে'));
            } catch (e) {
                if (!e.cancelled) this.flash(e.message);
            } finally {
                this.busy = false;
            }
        },

        /** "Show more replies": the next replies of a thread, rendered by the server. */
        async loadReplies(answerId, thread) {
            if (this.busy) return;
            this.busy = true;
            try {
                const data = await api('GET', withLang(`/api/answers/${answerId}/replies?after=${thread.after}`));
                const list = document.getElementById(`replies-${answerId}`);
                const seen = new Set([...list.querySelectorAll('[data-item]')].map((el) => el.dataset.item));
                const holder = document.createElement('div');
                holder.innerHTML = data.html;
                for (const el of [...holder.children]) if (!seen.has(el.dataset.item)) list.append(el);
                thread.after = data.last ?? thread.after;
                thread.more = data.more ? Math.max(1, thread.more - holder.childElementCount) : 0;
                this.refreshMine();
            } catch {
                this.flash(t('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।'));
            } finally {
                this.busy = false;
            }
        },

        /** The author saves an edited answer or reply: the server sends it back re-rendered. */
        /**
         * Fills an edit form's textarea from its item: the plain text at once (it works if the editor
         * never loads), and the formatted version for `x-rich` to open on.
         */
        editText(el) {
            const raw = (name) => el.closest('article').querySelector(`template[${name}]`)?.content.textContent ?? '';
            el.value = el.dataset.plain = raw('data-raw');
            el.dataset.html = raw('data-raw-html');
        },

        async saveAnswer(id, form) {
            this.busy = true;
            try {
                const data = await this.call('PATCH', `/api/answers/${id}`, formPayload(form));
                document.getElementById(`answer-${id}`)?.replaceWith(Object.assign(document.createElement('template'), { innerHTML: data.html.trim() }).content.firstChild);
                this.flash(t('সম্পাদনা সেভ হয়েছে'));
            } catch (e) {
                if (!e.cancelled) this.flash(e.message);
            } finally {
                this.busy = false;
            }
        },

        async savePost(id, form) {
            this.busy = true;
            try {
                const data = await this.call('PATCH', `/api/posts/${id}`, formPayload(form));
                const article = form.closest('article');
                document.getElementById('post-title').textContent = data.title;
                document.getElementById('post-body').innerHTML = data.body_html;
                document.getElementById('post-photos').innerHTML = data.photos_html ?? '';
                article.querySelector('template[data-raw-title]').innerHTML = '';
                article.querySelector('template[data-raw-title]').content.append(data.title);
                article.querySelector('template[data-raw]').innerHTML = '';
                article.querySelector('template[data-raw]').content.append(data.body ?? '');
                article.querySelector('template[data-raw-html]').innerHTML = '';
                article.querySelector('template[data-raw-html]').content.append(data.raw_html ?? '');
                article.querySelector('template[data-raw-photos]').innerHTML = '';
                article.querySelector('template[data-raw-photos]').content.append(JSON.stringify(data.photos ?? []));
                document.getElementById('post-edited')?.classList.remove('hidden');
                document.title = `${data.title} — ${document.title.split(' — ').pop()}`;
                Alpine.$data(article).editing = false;
                this.flash(t('সম্পাদনা সেভ হয়েছে'));
            } catch (e) {
                if (!e.cancelled) this.flash(e.message);
            } finally {
                this.busy = false;
            }
        },

        async submitPost(form) {
            this.formError = '';
            this.busy = true;
            try {
                const data = await this.call('POST', '/api/posts', formPayload(form));
                this.saveMember(data.member);
                location.href = data.post.url;
            } catch (e) {
                this.formError = e.message;
                this.busy = false;
            }
        },

        async submitAnswer(postId, form) {
            this.formError = '';
            this.busy = true;
            try {
                const data = await this.call('POST', `/api/posts/${postId}/answers`, formPayload(form));
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

        /**
         * Share a post: the phone's own share sheet where it works; elsewhere (desktop, and Facebook /
         * Messenger's in-app browsers, which have none) our sheet with WhatsApp, Facebook, Messenger, copy.
         */
        async sharePost(title, url, from = 'post') {
            if (navigator.share && isMobile() && !this.inApp) {
                track('post_shared', { meta: { channel: 'native', from } });
                try {
                    await navigator.share({ title, text: title, url });
                } catch {}
                return;
            }
            this.share = { title, url, from };
        },

        async shareTo(channel, title, url) {
            const from = this.share?.from ?? 'post';
            track('post_shared', { meta: { channel, from } });
            if (channel === 'copy' || (channel === 'messenger' && !isMobile())) {
                const ok = await copyText(`${title}\n${url}`);
                if (ok) this.share = null;
                this.flash(ok ? t('লিংক কপি হয়েছে! যাঁর দরকার তাঁকে পাঠান ✨') : t('কপি করা গেলো না। লিংকটা চেপে ধরে কপি করুন।'));
                return;
            }
            this.share = null;
            const href = shareLinks[channel](t(':title — জানা থাকলে উত্তর দিন 🙏', { title }), url);
            if (channel === 'messenger' || this.inApp) location.href = href; // in-app browsers block new windows
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

        /** Deletes the account (the server checks `confirm` is its code), then forgets it on this browser. */
        async deleteAccount(withContent) {
            if (this.busy) return;
            this.busy = true;
            try {
                await this.call('DELETE', '/api/members/me', { confirm: this.me, content: withContent });
            } catch (e) {
                this.busy = false;
                return this.flash(e.message);
            }
            this.member = null;
            this.owned = {};
            store.set(MEMBER, null);
            store.set(MARKS, []);
            store.set(SAVED, []);
            location.href = path('/feed?deleted=1');
        },

        // ---------- notifications ----------

        async refreshUnread() {
            if (!this.signedIn || document.getElementById('notice-list')) return; // that page loads the list itself
            try {
                this.unread = (await api('GET', '/api/notifications/unread', null, this.member.token)).count;
            } catch {}
        },

        /** The /notifications list; opening it marks everything read (the cards keep their "new" look until the next visit). */
        async loadNotifications(more = false) {
            if (!this.signedIn || this.busy) return;
            this.busy = true;
            try {
                const query = more && this.notices.next ? `?before=${this.notices.next}` : '';
                const data = await this.call('GET', `/api/notifications${query}`);
                const list = document.getElementById('notice-list');
                if (more) list.insertAdjacentHTML('beforeend', data.html);
                else list.innerHTML = data.html;
                this.notices = { state: list.children.length ? 'ready' : 'empty', next: data.next, email: data.email, prefs: data.prefs };
                if (!more && list.querySelector('.is-unread')) await this.call('POST', '/api/notifications/read');
                this.unread = 0;
            } catch (e) {
                if (!e.cancelled) this.notices.state = 'error';
            } finally {
                this.busy = false;
            }
        },

        async setEmailNotices(on) {
            try {
                this.notices.email = (await this.call('PATCH', '/api/notifications/settings', { email: on })).email;
                this.flash(on ? t('নতুন উত্তর এলে ইমেইলে জানাবো') : t('ইমেইল বন্ধ। এখানে তবু দেখতে পাবেন।'));
            } catch (e) {
                this.flash(e.message);
            }
        },

        /** One per-type switch (`site` or `email`): shown at once, put back if saving fails. */
        async setNoticePref(channel, type, on) {
            this.notices.prefs[channel][type] = on;
            try {
                this.notices.prefs = (await this.call('PATCH', '/api/notifications/settings', { prefs: { [channel]: { [type]: on } } })).prefs;
                this.flash(t('সেভ হয়েছে'));
            } catch (e) {
                this.notices.prefs[channel][type] = !on;
                this.flash(e.message);
            }
        },

        // The header's "Me" / "Log in": signed out, sign in right here instead of opening /me first.
        meClick(e) {
            if (this.signedIn) return;
            e.preventDefault();
            this.openLogin().then(() => this.openMe(), () => {});
        },

        navClick() {}, // already in the community; only quiz pages count these (site-nav.js)

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

/**
 * The home page's map: picking a division (a spot or a chip) shows its latest posts, rendered by the
 * server like every other card. Spots and chips are links to the division's feed without JavaScript.
 */
function divisionMap(divisions, lens, topics) {
    const FULL = [0, 0, 400, 552];
    const withTopic = (url, topic) => (topic ? `${url}${url.includes('?') ? '&' : '?'}category=${encodeURIComponent(topic)}` : url);
    const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
    return {
        spot: null, // picked division
        hover: null, // division under the pointer / keyboard focus (label)
        zoomed: null, // division the map is zoomed into
        districts: [], // its districts: { slug, name, short, url, x, y, label }
        view: FULL,
        district: null, // picked district
        dHover: null, // district under the pointer / keyboard focus
        mapState: 'idle', // districts: loading | ready | error
        topic: '', // category slug of the topic lens ('' = all)
        html: '',
        state: 'idle', // posts: loading (old posts dimmed) | slow (skeleton) | ready | empty | error
        init() {
            // District shapes come as plain HTML; their hover / picked look follows the state here.
            this.$watch('district', () => this.paintDistricts());
            this.$watch('dHover', () => this.paintDistricts());
        },
        get area() {
            return this.district ?? this.spot;
        },
        get name() {
            if (this.district) return this.districts.find((d) => d.slug === this.district)?.name ?? '';
            return divisions[this.spot]?.name ?? '';
        },
        get feedUrl() {
            const url = this.district ? this.districts.find((d) => d.slug === this.district)?.url : divisions[this.spot]?.url;
            return withTopic(url ?? path('/feed'), this.topic);
        },
        get askUrl() {
            return withTopic(path(this.district ? `/ask?area=${encodeURIComponent(this.district)}` : '/ask'), this.topic);
        },
        get topicName() {
            return topics[this.topic] ?? '';
        },
        level(slug) {
            return lens[this.topic || 'all']?.[slug]?.level ?? 0;
        },
        ariaFor(slug) {
            return `${divisions[slug].name}: ${lens[this.topic || 'all'][slug].label}`;
        },
        /** The hover label: a district when zoomed in, else a division (not the picked one: its posts are shown). */
        get tip() {
            if (this.zoomed) {
                const d = this.dHover && this.dHover !== this.district && this.districts.find((d) => d.slug === this.dHover);
                if (!d) return null;
                const [x, y, w, h] = this.view;
                return { ...d, style: `left: ${((d.x - x) / w) * 100}%; top: ${((d.y - y) / h) * 100}%` };
            }
            if (!this.hover || this.hover === this.spot) return null;
            const d = divisions[this.hover];
            return { name: d.name, ...lens[this.topic || 'all'][this.hover], style: `left: ${d.x}%; top: ${d.y}%` };
        },
        get announcement() {
            if (!this.spot) return '';
            if (this.state === 'ready') return t(':name: সাম্প্রতিক আলোচনা দেখানো হচ্ছে', { name: this.name });
            if (this.state === 'empty') return this.emptyText;
            if (this.state === 'error') return t('আনা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।');
            return '';
        },
        get emptyText() {
            return this.district ? t('এই জেলায় এখনো কোনো আলোচনা নেই। প্রথম প্রশ্নটা আপনিই করুন!') : t('এই বিভাগে এখনো কোনো আলোচনা নেই। প্রথম প্রশ্নটা আপনিই করুন!');
        },
        pick(slug) {
            if (this.spot === slug && this.zoomed === slug) return;
            this.spot = slug;
            this.district = null;
            track('community_clicked', { meta: { from: 'home_map', to: 'division', area: slug } });
            this.zoomTo(slug);
            this.load(slug);
        },
        pickDistrict(slug) {
            if (this.district === slug) return;
            this.district = slug;
            track('community_clicked', { meta: { from: 'home_map', to: 'district', area: slug } });
            // Phones: the posts are under the map; bring them into view without jumping.
            if (matchMedia('(max-width: 1023px)').matches) this.$nextTick(() => this.$refs.posts?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
            this.load(slug);
        },
        pickDistrictFrom(event) {
            const link = event.target.closest('[data-district]');
            if (!link) return;
            event.preventDefault();
            this.pickDistrict(link.dataset.district);
        },
        hoverDistrictFrom(event) {
            this.dHover = event.target.closest('[data-district]')?.dataset.district ?? null;
        },
        paintDistricts() {
            this.$refs.districts.querySelectorAll('[data-district]').forEach((link) => {
                const slug = link.dataset.district;
                link.firstElementChild?.classList.toggle('is-on', slug === this.district);
                link.firstElementChild?.classList.toggle('is-hover', slug === this.dHover);
            });
            this.$refs.districts.querySelectorAll('[data-name]').forEach((label) => label.classList.toggle('is-on', label.dataset.name === this.district || label.dataset.name === this.dHover));
        },
        /** Zooms the map into a division and draws its districts (fetched once, while the zoom runs). */
        setTopic(slug) {
            if (this.topic === slug) return;
            this.topic = slug;
            track('community_clicked', { meta: { from: 'home_map', to: 'topic', category: slug || 'all' } });
            if (this.zoomed) this.zoomTo(this.zoomed);
            if (this.spot) this.load(this.area);
        },
        close() {
            if (this.zoomed) this.zoomOut();
            this.spot = null;
        },
        /** Next / previous division, or district when zoomed in (wraps around). */
        step(by) {
            const list = this.zoomed ? this.districts.map((d) => d.slug) : Object.keys(divisions);
            const current = list.indexOf(this.zoomed ? this.district : this.spot);
            const next = list[(current + by + list.length) % list.length];
            this.zoomed ? this.pickDistrict(next) : this.pick(next);
        },
        swipeStart(event) {
            this._touch = [event.touches[0].clientX, event.touches[0].clientY];
        },
        swipeEnd(event) {
            if (!this._touch) return;
            const dx = event.changedTouches[0].clientX - this._touch[0];
            const dy = event.changedTouches[0].clientY - this._touch[1];
            this._touch = null;
            if (Math.abs(dx) > 60 && Math.abs(dx) > 2 * Math.abs(dy)) this.step(dx < 0 ? 1 : -1);
        },
        async zoomTo(slug) {
            this.zoomed = slug;
            this.mapState = 'loading';
            this.$refs.districts.innerHTML = '';
            this.districts = [];
            this.dHover = null;
            this.animateView(this.fit(slug));
            this._maps ??= {};
            const key = `${slug}|${this.topic}`;
            const topic = this.topic;
            try {
                this._maps[key] ??= await api('GET', withLang(withTopic(`/api/map/${encodeURIComponent(slug)}`, topic)));
            } catch {
                delete this._maps[key];
                if (this.zoomed === slug) Object.assign(this, { mapState: 'error', zoomed: null });
                return;
            }
            if (this.zoomed !== slug || this.topic !== topic) return; // zoomed out, elsewhere or another topic meanwhile
            const map = this._maps[key];
            this.$refs.districts.innerHTML = map.html;
            this.districts = map.districts;
            this.mapState = 'ready';
            this.paintDistricts();
        },
        zoomOut() {
            this.zoomed = null;
            this.district = null;
            this.dHover = null;
            this.districts = [];
            this.$refs.districts.innerHTML = '';
            this.animateView(FULL);
            if (this.spot) this.load(this.spot);
        },
        /** The division's box with some room around it, in the map's own proportions (as DivisionMap::fit). */
        fit(slug) {
            const b = this.$refs.map.querySelector(`[data-division="${slug}"]`).getBBox();
            let [w, h] = [b.width * 1.12, b.height * 1.12];
            const ratio = FULL[2] / FULL[3];
            if (w / h > ratio) h = w / ratio;
            else w = h * ratio;
            return [b.x + b.width / 2 - w / 2, b.y + b.height / 2 - h / 2, w, h];
        },
        animateView(to) {
            const svg = this.$refs.map;
            const from = this.view;
            this.view = to;
            cancelAnimationFrame(this._raf);
            if (still()) return svg.setAttribute('viewBox', to.join(' '));
            const start = performance.now();
            const step = (now) => {
                const p = Math.min(1, (now - start) / 600);
                const e = 1 - (1 - p) ** 3; // ease out
                svg.setAttribute('viewBox', from.map((v, i) => (v + (to[i] - v) * e).toFixed(2)).join(' '));
                if (p < 1) this._raf = requestAnimationFrame(step);
            };
            this._raf = requestAnimationFrame(step);
        },
        /** Latest posts of a division or district into the panel (cached per area). */
        async load(slug) {
            this._cache ??= {};
            const key = `${slug}|${this.topic}`;
            if (this._cache[key]) return Object.assign(this, this._cache[key]);
            // Keep what is shown (dimmed) while loading; a skeleton only if it takes a while.
            this.state = 'loading';
            clearTimeout(this._slow);
            this._slow = setTimeout(() => this.area === slug && this.state === 'loading' && !this.html && (this.state = 'slow'), 250);
            const current = () => `${this.area}|${this.topic}` === key; // not if another was picked meanwhile
            try {
                const data = await api('GET', withLang(withTopic(`/api/feed?limit=3&compact=1&area=${encodeURIComponent(slug)}`, this.topic)));
                this._cache[key] = { html: data.html, state: data.count ? 'ready' : 'empty' };
                if (current()) Object.assign(this, this._cache[key]);
            } catch {
                if (current()) this.state = 'error';
            }
        },
    };
}

/**
 * Home lists (latest / needs an answer / solved). Inside `divisionMap`, so they follow its topic: with
 * a topic, that topic's lists come from /api/feed (cached per tab and topic); without, the page's own.
 */
function homeLists() {
    return {
        tab: 'latest',
        filtered: {}, // `${tab}|${topic}` → HTML ('' = none, null = failed)
        listState: 'idle', // loading | slow | idle
        init() {
            this.$watch('topic', () => this.refresh());
            this.$watch('tab', () => this.refresh());
        },
        get key() {
            return `${this.tab}|${this.topic}`;
        },
        moreUrl(url) {
            return this.topic ? `${url}${url.includes('?') ? '&' : '?'}category=${encodeURIComponent(this.topic)}` : url;
        },
        async refresh() {
            const key = this.key;
            if (!this.topic || typeof this.filtered[key] === 'string') return;
            this.listState = 'loading';
            clearTimeout(this._slow);
            this._slow = setTimeout(() => this.listState === 'loading' && (this.listState = 'slow'), 250);
            try {
                const data = await api('GET', withLang(`/api/feed?limit=5&compact=1&tab=${this.tab}&category=${encodeURIComponent(this.topic)}`));
                this.filtered[key] = data.count ? data.html : '';
            } catch {
                this.filtered[key] = null;
            }
            if (this.key === key) this.listState = 'idle';
        },
    };
}

/** Home "happening now": shows the newest posts one by one; holds still under the pointer, focus, or reduced motion. */
function ticker(count) {
    return {
        i: 0,
        paused: false,
        init() {
            if (count < 2 || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            this._timer = setInterval(() => !this.paused && !document.hidden && (this.i = (this.i + 1) % count), 4000);
        },
        destroy() {
            clearInterval(this._timer);
        },
    };
}

/** A number that counts up from 0 once it scrolls into view (just the number under reduced motion). */
function countUp(target) {
    return {
        n: target,
        get shown() {
            return num(this.n);
        },
        init() {
            if (!target || matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
            this.n = 0;
            const seen = new IntersectionObserver(([entry]) => {
                if (!entry.isIntersecting) return;
                seen.disconnect();
                const start = performance.now();
                const step = (now) => {
                    const p = Math.min(1, (now - start) / 900);
                    this.n = Math.round(target * (1 - (1 - p) ** 3));
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });
            seen.observe(this.$el);
        },
    };
}

/** Ask page: questions like the title being typed, so people find an answer before asking again. */
function similarQuestions() {
    return {
        html: '',
        count: 0,
        shown: false,
        lookup(title) {
            clearTimeout(this._timer);
            const q = title.trim();
            if (q.length < 12) return Object.assign(this, { html: '', count: 0 });
            this._timer = setTimeout(async () => {
                try {
                    const data = await api('GET', withLang(`/api/posts/similar?q=${encodeURIComponent(q)}`));
                    if (q !== this.title.trim()) return; // typed on meanwhile
                    Object.assign(this, { html: data.html, count: data.count });
                    if (data.count && !this.shown) {
                        this.shown = true; // once per page: how often the box helps, not how often it updates
                        track('similar_shown', { meta: { results: data.count } });
                    }
                } catch {}
            }, 500);
        },
    };
}

Alpine.plugin(modal);
/**
 * `x-rich` on a composer's textarea (`x-rich="'post'"` adds the heading button): turns it into the
 * formatting editor (resources/js/editor.js, its own ~120 kB chunk) on the first touch or focus, or
 * once the browser is idle with `.eager` (the Ask page, where people came to write), or right away with
 * `.now` (edit forms, which open on the item's formatted text). Without JS, or until then, the plain
 * textarea works and is sent as plain text. `el._rich.focus()` focuses the editor.
 */
Alpine.directive('rich', (el, { expression, modifiers }, { evaluate, cleanup }) => {
    const headings = expression ? evaluate(expression) === 'post' : false;
    let started = false;
    const start = () => {
        if (started) return;
        started = true;
        import('./editor')
            .then(({ mountEditor }) => {
                el._rich = mountEditor(el, { headings });
                if (document.activeElement === el) el._rich.focus();
            })
            .catch(() => (started = false)); // offline: the textarea keeps working
    };
    el.addEventListener('pointerdown', start, { once: true });
    el.addEventListener('focus', start, { once: true });
    cleanup(() => el._rich?.editor.destroy()); // edit forms come and go with x-if
    if (modifiers.includes('now')) queueMicrotask(start); // after x-init has filled the textarea
    else if (modifiers.includes('eager')) (window.requestIdleCallback ?? ((fn) => setTimeout(fn, 1500)))(start, { timeout: 4000 });
});

Alpine.data('community', community);
Alpine.data('divisionMap', divisionMap);
Alpine.data('homeLists', homeLists);
Alpine.data('ticker', ticker);
Alpine.data('countUp', countUp);
Alpine.data('infinite', infinite);
Alpine.data('photoPicker', photoPicker);
Alpine.data('similarQuestions', similarQuestions);
window.Alpine = Alpine;
loadDictionary().then(() => Alpine.start());
