/** Facebook, Messenger and Instagram in-app browsers block downloads and most file shares. */
export const isInAppBrowser = () => /FBAN|FBAV|FB_IAB|Messenger|Instagram|Line\//i.test(navigator.userAgent);

export const isMobile = () => /Android|iPhone|iPad|iPod|Mobi/i.test(navigator.userAgent);

export function canShareFile(file) {
    try {
        return !!navigator.canShare?.({ files: [file] });
    } catch {
        return false;
    }
}

export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const el = Object.assign(document.createElement('textarea'), { value: text });
        el.setAttribute('readonly', '');
        el.style.cssText = 'position:fixed;opacity:0';
        document.body.append(el);
        el.select();
        const ok = document.execCommand('copy');
        el.remove();
        return ok;
    }
}

export const shareLinks = {
    whatsapp: (text, url) => `https://wa.me/?text=${encodeURIComponent(`${text}\n${url}`)}`,
    facebook: (text, url) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
    messenger: (text, url) => `fb-messenger://share/?link=${encodeURIComponent(url)}`,
};
