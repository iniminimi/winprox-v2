/**
 * Crop a single image (avatar) with Cropper.js, then return a File.
 * Used for worker portrait photos before client-side compress + Livewire upload.
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

/**
 * @param {File} file
 * @param {{
 *   aspectRatio?: number,
 *   title?: string,
 *   applyLabel?: string,
 *   cancelLabel?: string,
 * }} [options]
 * @returns {Promise<File|null>} cropped file, or null if cancelled
 */
export function wpCropImageFile(file, options = {}) {
    if (!(file instanceof File) || !file.type.startsWith('image/')) {
        return Promise.resolve(file);
    }

    const aspectRatio = options.aspectRatio ?? 1;
    const title = options.title ?? 'Crop';
    const applyLabel = options.applyLabel ?? 'Apply';
    const cancelLabel = options.cancelLabel ?? 'Cancel';

    return new Promise((resolve) => {
        const objectUrl = URL.createObjectURL(file);
        let settled = false;
        /** @type {Cropper|null} */
        let cropper = null;

        const root = document.createElement('div');
        root.className = 'wp-worker-photo-crop';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.innerHTML = `
            <div class="wp-worker-photo-crop__panel wp-card">
                <div class="wp-worker-photo-crop__head">
                    <h3 class="wp-section-title">${escapeHtml(title)}</h3>
                </div>
                <div class="wp-worker-photo-crop__stage">
                    <img alt="" class="wp-worker-photo-crop__image">
                </div>
                <div class="wp-worker-photo-crop__foot">
                    <button type="button" class="btn btn--ghost" data-wp-crop-cancel>${escapeHtml(cancelLabel)}</button>
                    <button type="button" class="btn btn--primary" data-wp-crop-apply>${escapeHtml(applyLabel)}</button>
                </div>
            </div>
        `;

        const img = /** @type {HTMLImageElement} */ (root.querySelector('.wp-worker-photo-crop__image'));
        const cancelBtn = /** @type {HTMLButtonElement} */ (root.querySelector('[data-wp-crop-cancel]'));
        const applyBtn = /** @type {HTMLButtonElement} */ (root.querySelector('[data-wp-crop-apply]'));

        const finish = (result) => {
            if (settled) {
                return;
            }
            settled = true;
            document.removeEventListener('keydown', onKeydown, true);
            cropper?.destroy();
            cropper = null;
            root.remove();
            URL.revokeObjectURL(objectUrl);
            resolve(result);
        };

        const onKeydown = (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            finish(null);
        };

        cancelBtn.addEventListener('click', () => finish(null));
        applyBtn.addEventListener('click', () => {
            if (!cropper) {
                finish(null);
                return;
            }

            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            if (!canvas) {
                finish(null);
                return;
            }

            canvas.toBlob((blob) => {
                if (!(blob instanceof Blob)) {
                    finish(null);
                    return;
                }

                const cropped = new File([blob], suggestJpegName(file.name), {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
                finish(cropped);
            }, 'image/jpeg', 0.9);
        });

        img.addEventListener('load', () => {
            cropper = new Cropper(img, {
                aspectRatio,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.85,
                responsive: true,
                background: false,
                movable: true,
                zoomable: true,
                rotatable: false,
                scalable: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        }, { once: true });

        img.src = objectUrl;
        document.addEventListener('keydown', onKeydown, true);
        document.body.appendChild(root);
        applyBtn.focus();
    });
}

/**
 * @param {string} name
 */
function suggestJpegName(name) {
    const base = name.replace(/\.[^.]+$/, '') || 'worker-photo';
    return `${base}.jpg`;
}

/**
 * @param {string} value
 */
function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

if (typeof window !== 'undefined') {
    window.wpCropImageFile = wpCropImageFile;
}
