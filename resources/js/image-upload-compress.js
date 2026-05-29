// Foto-upload golden path (WINPROX_RULES.md §7)
// - Client-side comprimeren (max 1600px, JPEG ~72%) vóór upload.
// - Directe preview via lokale objectURL.
// - Upload op de achtergrond (queue) via de Livewire-component (uploadMultiple).
// - GEEN wire:model op de file-input; document-level capture-listener overleeft Livewire-morphs.

const MAX_DIM = 1600;
const QUALITY = 0.72;

async function loadImage(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            const bitmap = await createImageBitmap(file);
            return { source: bitmap, width: bitmap.width, height: bitmap.height, cleanup: () => bitmap.close?.() };
        } catch (e) {
            // val terug op de Image-aanpak hieronder
        }
    }

    return await new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => resolve({
            source: img,
            width: img.naturalWidth,
            height: img.naturalHeight,
            cleanup: () => URL.revokeObjectURL(url),
        });
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('kon afbeelding niet laden'));
        };
        img.src = url;
    });
}

// Comprimeer één bestand naar een JPEG-File. Bij twijfel: geef het origineel terug.
window.wpCompressImageFile = async function wpCompressImageFile(file, options = {}) {
    const maxDim = options.maxDim || MAX_DIM;
    const quality = options.quality || QUALITY;

    if (!file || !file.type || !file.type.startsWith('image/')) {
        return file;
    }

    let loaded;
    try {
        loaded = await loadImage(file);
    } catch (e) {
        return file;
    }

    const { source, width, height, cleanup } = loaded;
    const scale = Math.min(1, maxDim / Math.max(width, height));
    const targetW = Math.max(1, Math.round(width * scale));
    const targetH = Math.max(1, Math.round(height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(source, 0, 0, targetW, targetH);
    cleanup?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob) {
        return file;
    }

    const name = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
};

function findComponent(el) {
    const root = el.closest('[wire\\:id]');
    if (!root || !window.Livewire) {
        return null;
    }
    return window.Livewire.find(root.getAttribute('wire:id'));
}

function setStatus(area, key) {
    const status = area.querySelector('[data-wp-photo-status]');
    if (status) {
        const text = status.dataset[key] || '';
        status.textContent = text;
    }
}

function addPreview(area, file) {
    const grid = area.querySelector('[data-wp-photo-previews]');
    if (!grid) {
        return;
    }
    const url = URL.createObjectURL(file);
    const thumb = document.createElement('div');
    thumb.className = 'wp-photo-thumb';
    const img = document.createElement('img');
    img.src = url;
    img.alt = '';
    img.onload = () => URL.revokeObjectURL(url);
    thumb.appendChild(img);
    grid.appendChild(thumb);
}

// Upload de volledige set verzamelde bestanden naar de Livewire-property.
function uploadAll(area) {
    const component = findComponent(area);
    if (!component) {
        return;
    }
    const model = area.dataset.wpModel || 'photos';
    const files = area.__wpFiles || [];

    area.__wpUploads = area.__wpUploads || [];
    setStatus(area, 'uploading');

    const promise = new Promise((resolve) => {
        const done = () => {
            setStatus(area, 'ready');
            resolve();
        };
        if (typeof component.uploadMultiple === 'function') {
            component.uploadMultiple(model, files, done, done);
        } else if (typeof component.upload === 'function' && files.length) {
            // Fallback: upload bestand voor bestand.
            let i = 0;
            const next = () => {
                if (i >= files.length) {
                    return done();
                }
                component.upload(model, files[i], () => { i += 1; next(); }, done);
            };
            next();
        } else {
            done();
        }
    });

    area.__wpUploads.push(promise);
}

async function handleFiles(area, input) {
    const max = parseInt(area.dataset.max || '4', 10);
    area.__wpFiles = area.__wpFiles || [];

    const incoming = Array.from(input.files || []);
    for (const file of incoming) {
        if (area.__wpFiles.length >= max) {
            break;
        }
        if (!file.type || !file.type.startsWith('image/')) {
            continue;
        }
        const compressed = await window.wpCompressImageFile(file);
        area.__wpFiles.push(compressed);
        addPreview(area, compressed);
    }

    input.value = '';

    if (area.__wpFiles.length) {
        uploadAll(area);
    }
}

// Wacht tot alle achtergrond-uploads voor de foto-area binnen $el klaar zijn,
// zodat de Livewire photos-array gevuld is vóór submit.
window.wpAwaitPhotoUploads = async function wpAwaitPhotoUploads(el) {
    if (!el) {
        return;
    }
    const area = el.matches && el.matches('[data-wp-photo-compress]')
        ? el
        : (el.querySelector ? el.querySelector('[data-wp-photo-compress]') : null);

    if (!area || !area.__wpUploads) {
        return;
    }
    await Promise.all(area.__wpUploads);
};

// DOCUMENT-level capture-listener: overleeft Livewire-morphs (de input zit in een
// wire:ignore-area, maar de listener hangt aan document i.p.v. aan het element).
document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }
    const area = input.closest('[data-wp-photo-compress]');
    if (!area) {
        return;
    }
    handleFiles(area, input);
}, true);
