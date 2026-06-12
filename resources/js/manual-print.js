/** @type {number} printable A4 height in CSS px (~297mm − margins at 96dpi) */
const WP_MANUAL_PRINTABLE_HEIGHT_PX = 1016;

function wpManualPreparePrint() {
    const root = document.querySelector('.wp-manual-root');
    const pageMarker = document.querySelector('.wp-manual-print-footer__page');

    if (!root || !pageMarker) {
        return;
    }

    const totalPages = Math.max(1, Math.ceil(root.scrollHeight / WP_MANUAL_PRINTABLE_HEIGHT_PX));
    pageMarker.dataset.totalPages = String(totalPages);
}

window.addEventListener('beforeprint', wpManualPreparePrint);
