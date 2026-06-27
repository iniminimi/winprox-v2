import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const editors = new Map();

function loadHtmlIntoQuill(quill, html) {
    if (!html) {
        return;
    }

    const delta = quill.clipboard.convert({ html });
    quill.setContents(delta, 'silent');
}

function syncQuillToTextarea(quill, textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) {
        return;
    }

    const html = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
    textarea.value = html;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function initEditor(container, textareaId, initialHtml) {
    if (editors.has(container)) {
        return editors.get(container);
    }

    const quill = new Quill(container, {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    if (initialHtml) {
        loadHtmlIntoQuill(quill, initialHtml);
    }

    quill.on('text-change', () => {
        syncQuillToTextarea(quill, textareaId);
    });

    syncQuillToTextarea(quill, textareaId);
    editors.set(container, quill);

    return quill;
}

function syncAllPromoEditors() {
    editors.forEach((quill, editorEl) => {
        const wrapper = editorEl.closest('[data-wp-promo-quill]');
        const textareaId = wrapper?.getAttribute('data-textarea-id');
        if (textareaId) {
            syncQuillToTextarea(quill, textareaId);
        }
    });
}

function mountEditors() {
    document.querySelectorAll('[data-wp-promo-quill]').forEach((wrapper) => {
        const editorEl = wrapper.querySelector('.wp-promo-quill-editor');
        const textareaId = wrapper.getAttribute('data-textarea-id');
        const textarea = textareaId ? document.getElementById(textareaId) : null;
        const initialHtml = textarea?.value || '';

        if (!editorEl || !textareaId || editors.has(editorEl)) {
            return;
        }

        initEditor(editorEl, textareaId, initialHtml);
    });
}

document.addEventListener('DOMContentLoaded', mountEditors);
document.addEventListener('livewire:navigated', mountEditors);
document.addEventListener('click', (event) => {
    if (!event.target.closest('[wire\\:click], button[type="submit"]')) {
        return;
    }

    syncAllPromoEditors();
}, true);

if (typeof Livewire !== 'undefined') {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', () => {
            syncAllPromoEditors();
        });

        Livewire.hook('morph.updated', () => {
            mountEditors();
        });
    });
}
