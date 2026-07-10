function resolveDateLang() {
    const meta = document.querySelector('meta[name="wp-date-locale"]');

    if (meta?.content) {
        return meta.content;
    }

    return document.documentElement.lang || 'nl-NL';
}

export function applyDateInputLocales(root = document) {
    const dateLang = resolveDateLang();

    root.querySelectorAll('input[type="date"].wp-date-input, input[type="date"].wp-input').forEach((input) => {
        input.lang = dateLang;
    });
}

document.addEventListener('DOMContentLoaded', () => applyDateInputLocales());

document.addEventListener('livewire:init', () => {
    applyDateInputLocales();

    if (typeof Livewire === 'undefined') {
        return;
    }

    Livewire.hook('morph.updated', ({ el }) => {
        applyDateInputLocales(el);
    });
});
