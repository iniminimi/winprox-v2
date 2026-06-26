import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const editors = new Map();

function syncQuillToTextarea(quill, textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea) {
        return;
    }

    textarea.value = quill.root.innerHTML;
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
        quill.root.innerHTML = initialHtml;
    }

    quill.on('text-change', () => {
        syncQuillToTextarea(quill, textareaId);
    });

    syncQuillToTextarea(quill, textareaId);
    editors.set(container, quill);

    return quill;
}

function mountEditors() {
    document.querySelectorAll('[data-wp-promo-quill]').forEach((wrapper) => {
        const editorEl = wrapper.querySelector('.wp-promo-quill-editor');
        const textareaId = wrapper.getAttribute('data-textarea-id');
        const initialHtml = wrapper.getAttribute('data-initial-html') || '';

        if (!editorEl || !textareaId || editors.has(editorEl)) {
            return;
        }

        initEditor(editorEl, textareaId, initialHtml);
    });
}

document.addEventListener('DOMContentLoaded', mountEditors);
document.addEventListener('livewire:navigated', mountEditors);

if (typeof Livewire !== 'undefined') {
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', () => {
            mountEditors();
        });
    });
}
