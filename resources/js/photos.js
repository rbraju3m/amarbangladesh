import { t } from './i18n';

/**
 * The photo picker in a composer (partials/photo-picker): up to MAX photos, each shrunk in the browser
 * (longest side 1600 px, JPEG, upright) and uploaded as soon as it's picked, so posting is instant.
 * The form sends the ids (`photos` in formPayload); App\Community\Photos re-encodes everything anyway.
 * Lives inside the `community` scope (sign-in, uploadPhoto) and the form's (`anon`, `photoCount`).
 */
export const MAX = 4;
const SIDE = 1600;
const QUALITY = 0.85;

/** A smaller, upright JPEG of a picked file; the file itself if the browser can't decode it. */
export async function shrink(file) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file; // e.g. HEIC on a browser that can't read it: the server decides (and refuses non-images)
    }
    const scale = Math.min(1, SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; // transparent PNGs get a white background, as on the server
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
    return blob ?? file;
}

/** POST /api/photos with upload progress (fetch has none). Resolves with the server's photo. */
export function send(blob, headers, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/photos');
        for (const [name, value] of Object.entries(headers)) xhr.setRequestHeader(name, value);
        xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
        xhr.onload = () => {
            let data = {};
            try {
                data = JSON.parse(xhr.responseText);
            } catch {}
            if (xhr.status >= 200 && xhr.status < 300) return resolve(data);
            const message = xhr.status === 429
                ? t('একটু থামুন, কিছুক্ষণ পরে আবার চেষ্টা করুন।')
                : (data.errors && Object.values(data.errors)[0]?.[0]) || t('ছবিটা আপলোড করা গেলো না। আবার চেষ্টা করুন।');
            reject(Object.assign(new Error(message), { status: xhr.status }));
        };
        xhr.onerror = () => reject(new Error(t('ছবিটা আপলোড করা গেলো না। ইন্টারনেট দেখে আবার চেষ্টা করুন।')));
        const body = new FormData();
        body.append('photo', blob, 'photo.jpg');
        xhr.send(body);
    });
}

/** Alpine component for partials/photo-picker. `initial`: the item's current photos (edit forms). */
export function photoPicker(initial = []) {
    return {
        max: MAX,
        photos: initial.map((p) => ({ key: `p${p.id}`, id: p.id, src: p.thumb, progress: 1, error: '' })),
        get uploading() {
            return this.photos.some((p) => !p.id && !p.error);
        },
        init() {
            this.sync();
            this.$watch('photos', () => this.sync());
            // The answer box is reset after posting: the photos went with the answer.
            this.$root.closest('form')?.addEventListener('reset', () => {
                this.photos.forEach((p) => p.src?.startsWith('blob:') && URL.revokeObjectURL(p.src));
                this.photos = [];
            });
        },
        // The form's own state (declared in its x-data) follows, so "anonymous" and submit can react.
        sync() {
            if ('photoCount' in this.$data) this.photoCount = this.photos.filter((p) => !p.error).length;
            this.$root.dataset.busy = this.uploading ? '1' : '';
        },
        async pick(event) {
            const files = [...event.target.files];
            event.target.value = ''; // the same file can be picked again after removing it
            if (!files.length) return;
            if (!this.signedIn) {
                try {
                    await this.openLogin();
                } catch {
                    return;
                }
            }
            const room = this.max - this.photos.filter((p) => !p.error).length;
            if (files.length > room) this.flash(t('একসাথে সর্বোচ্চ ৪টি ছবি দেওয়া যাবে।'));
            for (const file of files.slice(0, Math.max(0, room))) this.add(file);
        },
        async add(file) {
            const photo = { key: `n${Math.random().toString(36).slice(2)}`, id: null, src: URL.createObjectURL(file), progress: 0, error: '' };
            this.photos.push(photo);
            const item = () => this.photos.find((p) => p.key === photo.key); // the reactive copy
            try {
                const blob = await shrink(file);
                const data = await this.uploadPhoto(blob, (fraction) => item() && (item().progress = fraction));
                if (!item()) return; // removed while uploading; it is pruned with the other unattached uploads
                Object.assign(item(), { id: data.id, progress: 1 });
            } catch (e) {
                if (!item()) return;
                if (e.cancelled) return this.remove(photo.key);
                item().error = e.message;
            }
        },
        remove(key) {
            const photo = this.photos.find((p) => p.key === key);
            if (photo?.src?.startsWith('blob:')) URL.revokeObjectURL(photo.src);
            this.photos = this.photos.filter((p) => p.key !== key);
        },
    };
}
