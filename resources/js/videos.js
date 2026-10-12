import { num, t } from './i18n';
import { isInAppBrowser } from './share';

/**
 * The video picker in a composer (partials/video-picker): one video per post or top-level answer,
 * checked in the browser first (size, length), then sent in pieces (App\Community\Videos): POST
 * /api/videos {size}, then PUT /api/videos/{id}/chunk?offset= with the raw bytes. A dropped connection
 * waits and continues from where the server says it stands (409 carries `received`). The server
 * converts it after posting; the form sends `video` (formPayload in community.js), waiting for an
 * unfinished upload. Lives inside the `community` scope (sign-in, uploadVideo) and the form's (`anon`,
 * `hasVideo`).
 */
export const MAX_BYTES = 50 * 1024 * 1024; // Videos::MAX_BYTES
export const MAX_SECONDS = 60; // Videos::MAX_SECONDS
const RETRIES = 8; // network failures in a row before giving up

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const mb = (bytes) => (bytes / 1024 / 1024).toFixed(bytes < 10 * 1024 * 1024 ? 1 : 0);

/** One request with upload progress (fetch has none). Resolves with { status, data }; rejects only when no answer came. */
export function request(method, url, { headers = {}, body = null, onProgress = null, signal = null } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        for (const [name, value] of Object.entries(headers)) xhr.setRequestHeader(name, value);
        if (onProgress) xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded);
        xhr.onload = () => {
            let data = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch {}
            resolve({ status: xhr.status, data });
        };
        xhr.onerror = xhr.ontimeout = () => reject(Object.assign(new Error('network'), { network: true }));
        xhr.onabort = () => reject(Object.assign(new Error(''), { cancelled: true }));
        signal?.addEventListener('abort', () => xhr.abort());
        xhr.send(body);
    });
}

/** The server's message for a refused request. */
function refused({ status, data }) {
    if (status === 429) return t('একটু থামুন, কিছুক্ষণ পরে আবার চেষ্টা করুন।');
    return (data.errors && Object.values(data.errors)[0]?.[0]) || data.message || t('ভিডিওটা আপলোড করা গেলো না। আবার চেষ্টা করুন।');
}

/**
 * Sends `file` in pieces. `send(method, url, options)` is the community's signed-in request (adds the
 * token, signs in again on 401). Calls onProgress(bytes sent) and onStart(video) once the server knows it.
 * Resolves with the server's video once the last piece is in (status `waiting`).
 */
export async function upload(file, send, { onProgress, onStart, signal }) {
    const json = { 'Content-Type': 'application/json' };
    let res = await send('POST', '/api/videos', { headers: json, body: JSON.stringify({ size: file.size }), signal });
    if (res.status !== 201) throw new Error(refused(res));
    let video = res.data;
    onStart(video);
    let failures = 0;
    while (video.status === 'uploading') {
        const offset = video.received;
        const piece = file.slice(offset, offset + video.chunk);
        try {
            res = await send('PUT', `/api/videos/${video.id}/chunk?offset=${offset}`, {
                headers: { 'Content-Type': 'application/octet-stream' },
                body: piece,
                onProgress: (loaded) => onProgress(offset + loaded),
                signal,
            });
        } catch (e) {
            if (!e.network || ++failures > RETRIES) throw e.network ? new Error(offline()) : e;
            await waitForNetwork(failures, signal);
            // Where does the server stand? (the piece may have arrived before the connection dropped)
            try {
                res = await send('GET', `/api/videos/${video.id}`, { signal });
                if (res.status === 200) video = res.data;
            } catch (e2) {
                if (e2.cancelled) throw e2;
            }
            continue;
        }
        if (res.status === 200 || res.status === 409) {
            failures = 0;
            video = res.data;
            onProgress(video.received);
            continue;
        }
        if (res.status >= 500 && ++failures <= RETRIES) {
            await waitForNetwork(failures, signal);
            continue;
        }
        throw new Error(refused(res));
    }
    if (video.status === 'failed') throw new Error(t('ভিডিওটা চালানো গেলো না। অন্য একটা ভিডিও দিন।'));
    if (video.received !== video.size) throw new Error(t('ভিডিওটা আপলোড করা গেলো না। আবার চেষ্টা করুন।'));

    return video;
}

/** Waits a little longer after each failure, or until the phone says it's back online. */
function waitForNetwork(failures, signal) {
    return new Promise((resolve, reject) => {
        const done = () => {
            clearTimeout(timer);
            removeEventListener('online', done);
            resolve();
        };
        const timer = setTimeout(done, Math.min(30000, 1000 * 2 ** failures));
        addEventListener('online', done);
        signal?.addEventListener('abort', () => {
            clearTimeout(timer);
            reject(Object.assign(new Error(''), { cancelled: true }));
        });
    });
}

function offline() {
    return isInAppBrowser()
        ? t('ভিডিওটা আপলোড করা গেলো না। Facebook বা Messenger-এর ভেতরের ব্রাউজারে বড় ফাইল প্রায়ই আটকে যায়: ⋯ মেনু থেকে Chrome-এ খুলে আবার চেষ্টা করুন।')
        : t('ভিডিওটা আপলোড করা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।');
}

/** The length of a picked video in seconds, or null if this browser can't tell (the server checks anyway). */
function duration(src) {
    return new Promise((resolve) => {
        const el = document.createElement('video');
        const finish = (value) => {
            clearTimeout(timer);
            el.removeAttribute('src');
            el.load();
            resolve(Number.isFinite(value) && value > 0 ? value : null);
        };
        const timer = setTimeout(() => finish(null), 8000);
        el.preload = 'metadata';
        el.muted = true;
        el.onloadedmetadata = () => finish(el.duration);
        el.onerror = () => finish(null);
        el.src = src;
    });
}

/**
 * Alpine component for partials/video-picker. `initial`: the item's current video (edit forms, from
 * <template data-raw-video>), `available`: whether the server takes videos now (ffmpeg found).
 */
export function videoPicker(initial = null, available = true) {
    return {
        available,
        maxMb: mb(MAX_BYTES),
        // { id, status: uploading|uploaded|waiting|processing|ready|failed, attached, src, poster, sent, size, error, waiting }
        video: initial ? { ...initial, attached: true, src: null, sent: initial.size ?? 0, error: initial.status === 'failed' ? t('ভিডিওটা চালানো গেলো না। সরিয়ে দিন বা নতুন একটা দিন।') : '' } : null,
        _abort: null,
        _done: null, // the running upload, for the form to wait on

        get uploading() {
            return this.video?.status === 'uploading' && !this.video.error;
        },
        get percent() {
            return this.video?.size ? Math.min(100, Math.round((this.video.sent / this.video.size) * 100)) : 0;
        },
        /** What the tile says about the video, beside any error. */
        get label() {
            const v = this.video;
            if (!v || v.error) return '';
            if (v.status === 'uploading') return v.waiting ? t('আপলোড শেষ হলেই পাঠানো হবে…') : t('ভিডিও আপলোড হচ্ছে…');
            if (v.status === 'uploaded') return t('✓ ভিডিও আপলোড হয়েছে। পাঠানোর এক-দুই মিনিট পরে দেখা যাবে।');
            if (v.status === 'ready') return t('✓ ভিডিও যোগ করা আছে');
            return t('ভিডিও তৈরি হচ্ছে…');
        },
        get sentLabel() {
            return t(':sent / :size MB', { sent: num(mb(this.video.sent)), size: num(mb(this.video.size)) });
        },

        init() {
            this.sync();
            this.$watch('video', () => this.sync());
            this.$root._videoWait = () => this._done;
            this.$root.addEventListener('video-wait', () => this.video && (this.video.waiting = true));
            // The answer box is reset after posting: the video went with the answer.
            this.$root.closest('form')?.addEventListener('reset', () => this.clear());
            // Leaving the page mid-upload loses it: ask first.
            this._leave = (e) => {
                if (!this.uploading) return;
                e.preventDefault();
                e.returnValue = '';
            };
            addEventListener('beforeunload', this._leave);
        },
        destroy() {
            removeEventListener('beforeunload', this._leave);
            this._abort?.abort();
        },
        // The form's own state follows, so "anonymous" can lock and formPayload can find the id.
        sync() {
            if ('hasVideo' in this.$data) this.hasVideo = !!this.video && !this.video.error;
            this.$root.dataset.busy = this.uploading ? '1' : '';
            this.$root.dataset.error = this.video?.error && !this.video.attached ? '1' : '';
        },

        async pick(event) {
            const file = event.target.files[0];
            event.target.value = ''; // the same file can be picked again after removing it
            if (!file) return;
            if (!this.signedIn) {
                try {
                    await this.openLogin();
                } catch {
                    return;
                }
            }
            if (this.video) await this.remove();
            const src = URL.createObjectURL(file);
            const fail = (error) => (this.video = { id: null, status: 'uploading', src, size: file.size, sent: 0, error });
            if (file.type && !file.type.startsWith('video/')) return fail(t('এটা ভিডিও ফাইল না। একটা ভিডিও বেছে নিন।'));
            if (file.size > MAX_BYTES) return fail(t('ভিডিওটা অনেক বড় (:size MB)। :max MB-এর ছোট ভিডিও দিন।', { size: num(mb(file.size)), max: num(this.maxMb) }));
            const seconds = await duration(src);
            if (seconds && seconds > MAX_SECONDS + 0.5) return fail(t('ভিডিও ১ মিনিটের মধ্যে হতে হবে। ছোট করে আবার দিন।'));

            this.video = { id: null, status: 'uploading', src, size: file.size, sent: 0, error: '', waiting: false };
            const controller = (this._abort = new AbortController());
            const current = () => (this._abort === controller ? this.video : null); // still this upload?
            this._done = upload(file, (...args) => this.uploadVideo(...args), {
                signal: controller.signal,
                onStart: (v) => current() && (current().id = v.id),
                onProgress: (sent) => current() && (current().sent = sent),
            })
                .then((v) => {
                    if (!current()) return null;
                    Object.assign(current(), { id: v.id, status: 'uploaded', sent: v.size, waiting: false });
                    return v.id;
                })
                .catch((e) => {
                    if (current() && !e.cancelled) {
                        current().error = e.message;
                        current().waiting = false;
                    }
                    throw e;
                });
            this._done.catch(() => {}); // handled above; formPayload sees it again if it waits
        },

        /** Cancel an upload or take the video off. An unposted upload is dropped on the server too. */
        async remove() {
            const video = this.video;
            if (!video) return;
            this._abort?.abort();
            this._abort = null;
            this._done = null;
            this.clear();
            if (video.id && !video.attached) {
                try {
                    await this.uploadVideo('DELETE', `/api/videos/${video.id}`);
                } catch {}
            }
        },

        clear() {
            if (this.video?.src) URL.revokeObjectURL(this.video.src);
            this.video = null;
        },
    };
}
