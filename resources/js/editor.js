import { Editor } from '@tiptap/core';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import { t } from './i18n';

/**
 * The formatting composer (Tiptap), loaded as its own chunk by the `x-rich` directive in community.js.
 * The textarea stays in the form as the source of truth: it is hidden, and every change writes the
 * HTML into it (with `format=html` in the form's hidden field), so submitting, drafts and form.reset()
 * work as before. App\Community\RichText cleans whatever arrives, so the allowlist here is only for a
 * tidy editor: bold, italic, lists, links, and on posts a small heading (h3).
 */
const escape = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/** Plain text (typed before the editor loaded) as paragraphs. */
const fromText = (text) => text.split(/\n{2,}/).map((p) => `<p>${escape(p).replace(/\n/g, '<br>')}</p>`).join('');

const BUTTONS = [
    { key: 'bold', label: () => t('বোল্ড'), icon: '<b>B</b>', run: (c) => c.toggleBold(), active: 'bold' },
    { key: 'italic', label: () => t('ইটালিক'), icon: '<i class="font-serif">I</i>', run: (c) => c.toggleItalic(), active: 'italic' },
    { key: 'heading', label: () => t('ছোট শিরোনাম'), icon: '<b>H</b>', run: (c) => c.toggleHeading({ level: 3 }), active: ['heading', { level: 3 }], posts: true },
    { key: 'bullets', label: () => t('বুলেট তালিকা'), icon: '•≡', run: (c) => c.toggleBulletList(), active: 'bulletList' },
    { key: 'numbers', label: () => t('নম্বর তালিকা'), icon: '1.≡', run: (c) => c.toggleOrderedList(), active: 'orderedList' },
    { key: 'link', label: () => t('লিংক'), icon: '🔗', link: true, active: 'link' },
    { key: 'undo', label: () => t('আগের অবস্থায় ফেরান'), icon: '↶', run: (c) => c.undo(), can: (e) => e.can().undo() },
    { key: 'redo', label: () => t('আবার করুন'), icon: '↷', run: (c) => c.redo(), can: (e) => e.can().redo() },
];

export function mountEditor(textarea, { headings = false } = {}) {
    const form = textarea.form;
    const format = form.querySelector('input[name="format"]');
    const label = textarea.id && document.querySelector(`label[for="${textarea.id}"]`);

    const box = document.createElement('div');
    box.className = 'rich';
    const bar = document.createElement('div');
    bar.className = 'rich-bar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', t('লেখার ফরম্যাট'));
    const linkRow = document.createElement('div');
    linkRow.className = 'rich-link';
    linkRow.hidden = true;
    linkRow.innerHTML = `<input type="url" inputmode="url" placeholder="https://…" aria-label="${escape(t('লিংকের ঠিকানা'))}">`
        + `<button type="button" data-apply>${escape(t('যোগ করুন'))}</button><button type="button" data-remove>${escape(t('সরান'))}</button>`;
    const area = document.createElement('div');
    box.append(bar, linkRow, area);
    textarea.after(box);
    textarea.style.display = 'none'; // not `hidden`: .field sets display
    textarea.required = false; // a hidden required field would block the form; the submit buttons check length

    // An edit form opens on the item's saved formatting (data-html), unless the plain text was changed
    // while the editor loaded.
    const saved = textarea.dataset.html && textarea.value === textarea.dataset.plain ? textarea.dataset.html : null;

    let syncing = false;
    let buttons = [];
    const editor = new Editor({
        element: area,
        content: saved ?? (format?.value === 'html' ? textarea.value : fromText(textarea.value)),
        extensions: [
            StarterKit.configure({
                heading: headings ? { levels: [3] } : false,
                blockquote: false, code: false, codeBlock: false, horizontalRule: false, strike: false, underline: false,
                link: { openOnClick: false, autolink: true, defaultProtocol: 'https', protocols: ['mailto'], HTMLAttributes: { rel: 'nofollow noopener', target: '_blank' } },
            }),
            Placeholder.configure({ placeholder: textarea.placeholder }),
        ],
        editorProps: {
            attributes: {
                class: 'rich-text prose-text',
                role: 'textbox',
                'aria-multiline': 'true',
                ...(label ? { 'aria-labelledby': label.id || (label.id = `${textarea.id}-label`) } : { 'aria-label': textarea.placeholder }),
            },
        },
        onUpdate: ({ editor }) => {
            syncing = true;
            textarea.value = editor.isEmpty ? '' : editor.getHTML();
            if (format) format.value = 'html';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            syncing = false;
        },
        onTransaction: () => paint(),
    });
    if (format) format.value = 'html';
    textarea.value = editor.isEmpty ? '' : editor.getHTML();

    // Someone else changed the textarea (form.reset() after posting, a restored draft): follow it.
    textarea.addEventListener('input', () => {
        if (syncing) return;
        editor.commands.setContent(format?.value === 'html' ? textarea.value : fromText(textarea.value), { emitUpdate: false });
        if (format) format.value = 'html';
    });
    label?.addEventListener('click', (e) => {
        e.preventDefault();
        editor.commands.focus();
    });

    buttons = BUTTONS.filter((b) => headings || !b.posts).map((b) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'rich-btn';
        el.innerHTML = b.icon;
        el.title = b.label();
        el.setAttribute('aria-label', b.label());
        // Keep the selection (and the phone keyboard) in the editor.
        el.addEventListener('mousedown', (e) => e.preventDefault());
        el.addEventListener('click', () => (b.link ? toggleLink() : b.run(editor.chain().focus()).run()));
        bar.append(el);
        return [b, el];
    });

    function paint() {
        for (const [b, el] of buttons) {
            if (b.active) el.setAttribute('aria-pressed', String(Array.isArray(b.active) ? editor.isActive(...b.active) : editor.isActive(b.active)));
            if (b.can) el.disabled = !b.can(editor);
        }
    }

    const urlInput = linkRow.querySelector('input');
    function toggleLink() {
        linkRow.hidden = !linkRow.hidden;
        if (linkRow.hidden) return editor.commands.focus();
        urlInput.value = editor.getAttributes('link').href ?? '';
        urlInput.focus();
    }
    function applyLink() {
        let href = urlInput.value.trim();
        if (href && !/^(https?:|mailto:)/i.test(href)) href = (href.includes('@') && !href.includes('/') ? 'mailto:' : 'https://') + href;
        const chain = editor.chain().focus().extendMarkRange('link');
        if (!href) chain.unsetLink().run();
        else if (editor.state.selection.empty && !editor.isActive('link')) chain.insertContent(`<a href="${escape(href)}">${escape(urlInput.value.trim())}</a> `).run();
        else chain.setLink({ href }).run();
        linkRow.hidden = true;
    }
    linkRow.querySelector('[data-apply]').addEventListener('click', applyLink);
    linkRow.querySelector('[data-remove]').addEventListener('click', () => {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        linkRow.hidden = true;
    });
    urlInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyLink();
        } else if (e.key === 'Escape') {
            linkRow.hidden = true;
            editor.commands.focus();
        }
    });

    paint();
    return { editor, focus: () => editor.commands.focus('end') };
}
