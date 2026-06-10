/**
 * Resize/compress issue photos in the browser before Livewire upload.
 * Previews appear immediately; compress + upload run in a per-area queue (non-blocking UI).
 *
 * Baseline: winprox V1 (issue-photo-upload golden path). Do not regress without diffing V1.
 */

const DEFAULT_MAX_DIMENSION = 1600;
const DEFAULT_JPEG_QUALITY = 0.72;
const DEFAULT_MIME = 'image/jpeg';

const WIRE_MODEL_ATTRS = ['wire:model', 'wire:model.live', 'wire:model.defer', 'wire:model.blur'];

const PREVIEW_THUMB_STYLE = {
    wrap: 'width:96px;height:96px;position:relative;overflow:hidden;border-radius:8px;border:2px solid var(--wp-border, #d1fae5);',
    img: 'width:96px;height:96px;object-fit:cover;',
};

/**
 * @param {File} file
 * @param {{ maxDimension?: number, quality?: number }} [options]
 * @returns {Promise<File>}
 */
export async function wpCompressImageFile(file, options = {}) {
    if (!(file instanceof File) || !file.type.startsWith('image/')) {
        return file;
    }

    const maxDimension = options.maxDimension ?? DEFAULT_MAX_DIMENSION;
    const quality = options.quality ?? DEFAULT_JPEG_QUALITY;

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }

    let { width, height } = bitmap;
    const longest = Math.max(width, height);
    if (longest > maxDimension) {
        const scale = maxDimension / longest;
        width = Math.round(width * scale);
        height = Math.round(height * scale);
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        bitmap.close?.();
        return file;
    }

    ctx.drawImage(bitmap, 0, 0, width, height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => {
        canvas.toBlob(resolve, DEFAULT_MIME, quality);
    });

    if (!blob) {
        return file;
    }

    const baseName = (file.name || 'photo').replace(/\.[^.]+$/i, '') || 'photo';

    return new File([blob], `${baseName}.jpg`, {
        type: DEFAULT_MIME,
        lastModified: Date.now(),
    });
}

/**
 * @param {HTMLInputElement} input
 * @returns {string}
 */
function wpExtractWireModelProperty(input) {
    for (const attr of input.attributes) {
        if (!attr.name.startsWith('wire:model')) {
            continue;
        }

        const value = attr.value.trim();
        if (value !== '') {
            return value.split('.')[0].replace(/\[.*$/, '');
        }
    }

    return '';
}

/**
 * @param {HTMLInputElement} input
 */
function wpStripWireModelAttributes(input) {
    WIRE_MODEL_ATTRS.forEach((name) => {
        if (input.hasAttribute(name)) {
            input.removeAttribute(name);
        }
    });
}

/**
 * @param {string} propertyName
 * @param {HTMLInputElement} [fallbackInput]
 * @returns {import('livewire').Component | null}
 */
function wpResolveUploadComponent(propertyName, fallbackInput) {
    const candidates = [];

    if (fallbackInput) {
        candidates.push(fallbackInput);
    }

    document.querySelectorAll(`input[data-wp-photo-upload-prop="${propertyName}"]`).forEach((node) => {
        if (node instanceof HTMLInputElement) {
            candidates.push(node);
        }
    });

    for (const input of candidates) {
        let component = null;

        const root = input.closest('[wire\\:id]');
        const componentId = root?.getAttribute('wire:id');
        if (componentId) {
            component = window.Livewire?.find(componentId);
        }

        if (!component?.upload) {
            document.querySelectorAll('[wire\\:id]').forEach((node) => {
                if (!node.contains(input)) {
                    return;
                }

                const id = node.getAttribute('wire:id');
                const candidate = id && window.Livewire?.find(id);

                if (candidate && typeof candidate.upload === 'function') {
                    component = candidate;
                }
            });
        }

        if (component && typeof component.upload === 'function') {
            return component;
        }
    }

    return null;
}

/**
 * @param {HTMLElement} area
 */
function wpSyncPhotoPicker(area) {
    const max = Number(area.dataset.wpPhotoMax || '4');
    const previewRoot = area.querySelector('[data-wp-photo-preview-root]');
    const picker = area.querySelector('[data-wp-photo-picker]');

    if (!picker || !previewRoot) {
        return;
    }

    const count = previewRoot.children.length;
    picker.hidden = count >= max;
}

/**
 * @param {HTMLElement} previewRoot
 */
function wpReindexPhotoPreviews(previewRoot) {
    Array.from(previewRoot.children).forEach((child, index) => {
        if (child instanceof HTMLElement) {
            child.dataset.wpPhotoIndex = String(index);
        }
    });
}

/**
 * @param {HTMLElement} area
 * @param {HTMLElement} previewRoot
 * @param {string} objectUrl
 * @param {import('livewire').Component} component
 * @param {string} removeMethod
 * @returns {HTMLElement}
 */
function wpAppendLocalPhotoPreview(area, previewRoot, objectUrl, component, removeMethod) {
    const index = previewRoot.children.length;
    const alt = area.querySelector('input[type="file"]')?.getAttribute('aria-label') || 'Photo';

    const wrap = document.createElement('div');
    wrap.dataset.wpPhotoIndex = String(index);
    wrap.style.cssText = PREVIEW_THUMB_STYLE.wrap;

    const img = document.createElement('img');
    img.src = objectUrl;
    img.alt = alt;
    img.style.cssText = PREVIEW_THUMB_STYLE.img;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = '\u2715';
    btn.className = 'wp-photo-remove';
    btn.addEventListener('click', () => {
        const currentIndex = Number(wrap.dataset.wpPhotoIndex);
        const storedUrl = wrap.dataset.wpPhotoObjectUrl;

        if (storedUrl) {
            URL.revokeObjectURL(storedUrl);
        }

        wrap.remove();
        wpReindexPhotoPreviews(previewRoot);
        wpSyncPhotoPicker(area);

        if (wrap.dataset.wpPhotoUploaded === '1' && typeof component.call === 'function') {
            component.call(removeMethod, currentIndex);
        }
    });

    wrap.dataset.wpPhotoObjectUrl = objectUrl;
    wrap.append(img, btn);
    previewRoot.appendChild(wrap);
    wpSyncPhotoPicker(area);

    return wrap;
}

/**
 * @param {import('livewire').Component} component
 * @param {string} propertyName
 * @param {File[]} files
 * @returns {Promise<void>}
 */
function wpLivewireUploadFiles(component, propertyName, files) {
    if (files.length === 0) {
        return Promise.resolve();
    }

    if (files.length > 1 && typeof component.uploadMultiple === 'function') {
        return new Promise((resolve, reject) => {
            component.uploadMultiple(
                propertyName,
                files,
                () => resolve(),
                (error) => reject(error || new Error('upload failed')),
            );
        });
    }

    return (async () => {
        for (const file of files) {
            await new Promise((resolve, reject) => {
                component.upload(propertyName, file, () => resolve(), (error) => reject(error || new Error('upload failed')));
            });
        }
    })();
}

/**
 * @param {HTMLElement} area
 * @param {() => Promise<void>} job
 */
function wpSchedulePhotoJob(area, job) {
    const previous = area._wpPhotoUploadChain ?? Promise.resolve();
    area._wpPhotoUploadChain = previous.then(job).catch((error) => {
        console.error('[wp-photo-compress]', error);
    });

    return area._wpPhotoUploadChain;
}

/**
 * Show a temporary flash message for upload errors
 * @param {string} message
 */
function wpShowUploadError(message) {
    const flash = document.createElement('div');
    flash.className = 'wp-flash wp-flash--danger';
    flash.style.cssText = 'position: fixed; top: 10px; left: 50%; transform: translateX(-50%); z-index: 9999; text-align: center; max-width: 90%;';
    flash.textContent = message;
    document.body.appendChild(flash);

    setTimeout(() => {
        flash.remove();
    }, 5000);
}

/**
 * @param {HTMLElement} area
 * @param {HTMLInputElement} input
 * @param {string} propertyName
 * @param {File[]} files
 */
async function wpProcessPhotoBatch(area, input, propertyName, files) {
    const previewRoot = area.querySelector('[data-wp-photo-preview-root]');
    const removeMethod = area.dataset.wpPhotoRemoveMethod || 'removePhoto';
    const component = wpResolveUploadComponent(propertyName, input);

    if (!component || !previewRoot) {
        return;
    }

    const entries = files.map((file) => {
        const objectUrl = URL.createObjectURL(file);
        const wrap = wpAppendLocalPhotoPreview(area, previewRoot, objectUrl, component, removeMethod);

        return { file, objectUrl, wrap };
    });

    try {
        const compressedFiles = await Promise.all(
            files.map((file) => wpCompressImageFile(file)),
        );

        await wpLivewireUploadFiles(component, propertyName, compressedFiles);

        entries.forEach(({ wrap }) => {
            wrap.dataset.wpPhotoUploaded = '1';
        });
    } catch (error) {
        entries.forEach(({ wrap, objectUrl }) => {
            URL.revokeObjectURL(objectUrl);
            wrap.remove();
        });
        wpReindexPhotoPreviews(previewRoot);
        wpSyncPhotoPicker(area);

        // Show user-friendly error message
        const localeKey = 'portal.unit.upload_failed_offline';
        const errorMessage = window.__translations?.[localeKey] || localeKey;
        wpShowUploadError(errorMessage);

        // Reset the upload chain so subsequent uploads can work
        area._wpPhotoUploadChain = Promise.resolve();

        console.error('[wp-photo-compress] Upload failed:', error);
    }
}

/**
 * @param {HTMLInputElement} element
 */
function wpBindCompressedPhotoInput(element) {
    if (!(element instanceof HTMLInputElement)) {
        return;
    }

    const accept = (element.getAttribute('accept') || '').toLowerCase();
    if (accept !== '' && !accept.includes('image')) {
        return;
    }

    const propertyName = element.getAttribute('data-wp-photo-upload-prop')
        || wpExtractWireModelProperty(element);

    if (!propertyName || !/photo/i.test(propertyName)) {
        return;
    }

    wpStripWireModelAttributes(element);
    element.setAttribute('data-wp-photo-compress', '');
    element.setAttribute('data-wp-photo-upload-prop', propertyName);
}

/**
 * @param {ParentNode} [root]
 */
export function wpPrepareIssuePhotoInputs(root = document) {
    root.querySelectorAll('input[type="file"]').forEach((element) => {
        wpBindCompressedPhotoInput(element);
    });
}

/**
 * Wait until all queued compress/upload jobs finished (call before form submit).
 *
 * @param {HTMLElement} formOrArea
 * @param {{ timeoutMs?: number }} [options]
 * @returns {Promise<void>}
 */
export async function wpAwaitPhotoUploads(formOrArea, options = {}) {
    const timeoutMs = options.timeoutMs ?? 90_000;
    /** @type {HTMLElement[]} */
    const areas = [];

    if (formOrArea instanceof HTMLElement) {
        if (formOrArea.classList.contains('wp-photo-upload-area')) {
            areas.push(formOrArea);
        } else {
            formOrArea.querySelectorAll('.wp-photo-upload-area').forEach((node) => {
                if (node instanceof HTMLElement) {
                    areas.push(node);
                }
            });
        }
    }

    if (areas.length === 0) {
        return;
    }

    const awaitChains = () => Promise.all(
        areas.map((area) => area._wpPhotoUploadChain ?? Promise.resolve()),
    );

    let timeoutId;
    const timeout = new Promise((_, reject) => {
        timeoutId = setTimeout(() => reject(new Error('wp-photo-upload-timeout')), timeoutMs);
    });

    try {
        await Promise.race([awaitChains(), timeout]);
    } catch (error) {
        if (error instanceof Error && error.message === 'wp-photo-upload-timeout') {
            console.warn('[wp-photo-compress] upload queue timeout; continuing submit.');
        } else {
            throw error;
        }
    } finally {
        clearTimeout(timeoutId);
    }
}

export function wpUploadCompressedPhotos(input, propertyName = 'photos') {
    const area = input.closest('.wp-photo-upload-area');

    if (!(area instanceof HTMLElement)) {
        return;
    }

    const previewRoot = area.querySelector('[data-wp-photo-preview-root]');
    const max = Number(area.dataset.wpPhotoMax || '4');
    const slotsLeft = Math.max(0, max - (previewRoot?.children.length ?? 0));
    const files = Array.from(input.files || []).slice(0, slotsLeft);
    input.value = '';

    if (files.length === 0) {
        return;
    }

    wpSchedulePhotoJob(area, () => wpProcessPhotoBatch(area, input, propertyName, files));
}

function wpClearAllPhotoPreviews() {
    document.querySelectorAll('[data-wp-photo-preview-root]').forEach((root) => {
        Array.from(root.children).forEach((child) => {
            if (child instanceof HTMLElement && child.dataset.wpPhotoObjectUrl) {
                URL.revokeObjectURL(child.dataset.wpPhotoObjectUrl);
            }
        });
        root.innerHTML = '';
    });

    document.querySelectorAll('.wp-photo-upload-area').forEach((area) => {
        if (area instanceof HTMLElement) {
            area._wpPhotoUploadChain = Promise.resolve();
            wpSyncPhotoPicker(area);
        }
    });
}

function wpRefreshAllPhotoUploadAreas() {
    wpPrepareIssuePhotoInputs();
    document.querySelectorAll('.wp-photo-upload-area').forEach((area) => {
        if (area instanceof HTMLElement) {
            wpSyncPhotoPicker(area);
        }
    });
}

function handleCompressedPhotoChange(event) {
    const input = event.target;

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    if (!input.hasAttribute('data-wp-photo-compress')) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    const propertyName = input.getAttribute('data-wp-photo-upload-prop') || 'photos';

    wpUploadCompressedPhotos(input, propertyName);
}

function wpInitPhotoUploadHooks() {
    if (window.Livewire?.hook) {
        Livewire.hook('morph.added', ({ el }) => {
            wpPrepareIssuePhotoInputs(el);

            if (el instanceof HTMLElement) {
                el.querySelectorAll?.('.wp-photo-upload-area').forEach((area) => {
                    if (area instanceof HTMLElement) {
                        wpSyncPhotoPicker(area);
                    }
                });
            }
        });
    }

    if (window.Livewire?.on) {
        Livewire.on('wp-clear-photo-previews', wpClearAllPhotoPreviews);
        Livewire.on('wp-prepare-photo-inputs', wpRefreshAllPhotoUploadAreas);
    }
}

export function wpInitCompressedPhotoInputs() {
    if (typeof window !== 'undefined') {
        window.wpPrepareIssuePhotoInputs = wpPrepareIssuePhotoInputs;
        window.wpUploadCompressedPhotos = wpUploadCompressedPhotos;
        window.wpRefreshAllPhotoUploadAreas = wpRefreshAllPhotoUploadAreas;
        window.wpAwaitPhotoUploads = wpAwaitPhotoUploads;
        window.wpCompressImageFile = wpCompressImageFile;
    }

    if (window.__wpPhotoCompressInit) {
        wpRefreshAllPhotoUploadAreas();
        return;
    }
    window.__wpPhotoCompressInit = true;

    document.addEventListener('change', handleCompressedPhotoChange, true);
    window.addEventListener('wp-clear-photo-previews', wpClearAllPhotoPreviews);
    window.addEventListener('wp-prepare-photo-inputs', wpRefreshAllPhotoUploadAreas);

    wpRefreshAllPhotoUploadAreas();

    document.addEventListener('livewire:init', () => {
        wpRefreshAllPhotoUploadAreas();
        wpInitPhotoUploadHooks();
    }, { once: true });

    if (window.Livewire) {
        wpInitPhotoUploadHooks();
    }
}

wpInitCompressedPhotoInputs();
