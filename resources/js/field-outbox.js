/**
 * Field outbox: queue worker mutations in IndexedDB and FIFO-sync them.
 * Does not talk to Livewire. Photos stay as Blob/File until multipart POST.
 */

const DB_NAME = 'wp-field-outbox';
const DB_VERSION = 1;
const STORE = 'items';
const MAX_ITEMS = 20;
const MAX_BYTES = 20 * 1024 * 1024;
const RETRY_BASE_MS = 2000;

/** @type {'online'|'saved'|'syncing'|'synced'|'error'} */
let currentStatus = navigator.onLine ? 'online' : 'saved';
let drainPromise = null;
let retryTimer = null;

function t(key, fallback) {
    return window.__translations?.[key] || fallback || key;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function syncUrl() {
    return window.__wpFieldSync?.url || '/portal/field-sync';
}

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE)) {
                const store = db.createObjectStore(STORE, { keyPath: 'client_id' });
                store.createIndex('queued_at', 'queued_at', { unique: false });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error || new Error('indexeddb'));
    });
}

/**
 * @param {(store: IDBObjectStore) => IDBRequest} fn
 */
async function withStore(mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const store = tx.objectStore(STORE);
        const request = fn(store);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function allItems() {
    const rows = await withStore('readonly', (store) => store.getAll());
    return (rows || []).sort((a, b) => String(a.queued_at).localeCompare(String(b.queued_at)));
}

function estimateBytes(item) {
    let bytes = 256 + JSON.stringify(item.payload || {}).length;
    for (const photo of item.photos || []) {
        bytes += photo?.size || 0;
    }
    return bytes;
}

function emitStatus(status, extra = {}) {
    currentStatus = status;
    const detail = { status, ...extra };
    window.dispatchEvent(new CustomEvent('wp-field-sync-status', { detail }));
    const el = document.querySelector('[data-wp-field-sync-status]');
    if (!el) {
        return;
    }

    const labels = {
        online: t('portal.field_sync.online', 'Online'),
        saved: t('portal.field_sync.saved_on_device', 'Opgeslagen op toestel'),
        syncing: t('portal.field_sync.syncing', 'Synchroniseren'),
        synced: t('portal.field_sync.synced', 'Gesynchroniseerd'),
        error: t('portal.field_sync.error', 'Synchronisatie mislukt'),
    };

    el.textContent = labels[status] || labels.online;
    el.hidden = status === 'online' && (extra.pending || 0) === 0;
    el.dataset.state = status;
    el.classList.toggle('wp-pill--new', status === 'saved' || status === 'syncing');
    el.classList.toggle('wp-pill--progress', status === 'syncing');
    el.classList.toggle('wp-pill--done', status === 'synced');
    el.classList.toggle('wp-pill--closed', status === 'error');
}

/**
 * @param {{ type: string, unitToken: string, payload?: object, photos?: Blob[], onQueued?: (item: object) => void }} spec
 */
export async function wpFieldEnqueue(spec) {
    const items = await allItems();
    if (items.length >= MAX_ITEMS) {
        throw new Error(t('portal.field_sync.queue_full', 'Te veel offline acties. Maak verbinding om te synchroniseren.'));
    }

    const photos = (spec.photos || []).filter((file) => file instanceof Blob);
    const item = {
        client_id: crypto.randomUUID(),
        type: spec.type,
        unit_token: spec.unitToken,
        queued_at: new Date().toISOString(),
        payload: spec.payload || {},
        photos,
    };

    const total = items.reduce((sum, row) => sum + estimateBytes(row), 0) + estimateBytes(item);
    if (total > MAX_BYTES) {
        throw new Error(t('portal.field_sync.queue_full', 'Te veel offline acties. Maak verbinding om te synchroniseren.'));
    }

    await withStore('readwrite', (store) => store.put(item));
    spec.onQueued?.(item);
    emitStatus('saved', { pending: items.length + 1 });
    wpFieldDrain();

    return item;
}

async function postItem(item) {
    const form = new FormData();
    form.append('client_id', item.client_id);
    form.append('type', item.type);
    form.append('unit_token', item.unit_token);
    form.append('payload', JSON.stringify({
        ...(item.payload || {}),
        queued_at: item.queued_at,
    }));

    (item.photos || []).forEach((photo, index) => {
        const name = photo instanceof File && photo.name ? photo.name : `photo-${index + 1}.jpg`;
        form.append('photos[]', photo, name);
    });

    const response = await fetch(syncUrl(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: form,
    });

    if (response.status === 419) {
        throw new Error('csrf');
    }

    let body = {};
    try {
        body = await response.json();
    } catch {
        body = {};
    }

    if (!response.ok && response.status >= 500) {
        throw new Error('server');
    }

    return { response, body };
}

export async function wpFieldDrain() {
    if (drainPromise) {
        return drainPromise;
    }

    drainPromise = (async () => {
        const items = await allItems();
        if (items.length === 0) {
            emitStatus(navigator.onLine ? 'online' : 'saved', { pending: 0 });
            return;
        }

        if (!navigator.onLine) {
            emitStatus('saved', { pending: items.length });
            return;
        }

        emitStatus('syncing', { pending: items.length });

        for (const item of items) {
            try {
                const { response, body } = await postItem(item);
                if (response.status >= 500 || response.status === 419) {
                    emitStatus('error', { pending: (await allItems()).length, error: body.error });
                    scheduleRetry();
                    return;
                }

                if (body.ok === false && response.status >= 400 && response.status < 500) {
                    await withStore('readwrite', (store) => store.delete(item.client_id));
                    window.dispatchEvent(new CustomEvent('wp-field-sync-item', {
                        detail: { item, ok: false, body },
                    }));
                    continue;
                }

                await withStore('readwrite', (store) => store.delete(item.client_id));
                window.dispatchEvent(new CustomEvent('wp-field-sync-item', {
                    detail: { item, ok: true, body },
                }));
            } catch {
                emitStatus('error', { pending: (await allItems()).length });
                scheduleRetry();
                return;
            }
        }

        emitStatus('synced', { pending: 0 });
        setTimeout(() => {
            if (currentStatus === 'synced') {
                emitStatus('online', { pending: 0 });
            }
        }, 2500);
    })().finally(() => {
        drainPromise = null;
    });

    return drainPromise;
}

function scheduleRetry() {
    clearTimeout(retryTimer);
    retryTimer = setTimeout(() => {
        if (navigator.onLine) {
            wpFieldDrain();
        }
    }, RETRY_BASE_MS);
}

export function wpCollectLocalPhotos(root) {
    if (!(root instanceof HTMLElement)) {
        return [];
    }

    /** @type {HTMLElement[]} */
    const areas = [];
    if (root.classList.contains('wp-photo-upload-area')) {
        areas.push(root);
    } else {
        root.querySelectorAll('.wp-photo-upload-area').forEach((node) => {
            if (node instanceof HTMLElement) {
                areas.push(node);
            }
        });
    }

    /** @type {File[]} */
    const files = [];
    areas.forEach((area) => {
        const local = Array.isArray(area._wpLocalPhotos) ? area._wpLocalPhotos : [];
        local.forEach((file) => {
            if (file instanceof Blob) {
                files.push(file);
            }
        });
    });

    return files;
}

function markTaskLocal(taskId, status) {
    document.querySelectorAll(`[data-wp-task="${taskId}"]`).forEach((node) => {
        node.setAttribute('data-wp-local-status', status);
    });
}

function unitToken() {
    return window.__wpFieldSync?.unitToken || '';
}

export async function wpFieldStartTask(taskId) {
    const item = await wpFieldEnqueue({
        type: 'task.start',
        unitToken: unitToken(),
        payload: { task_id: Number(taskId) },
        onQueued: () => markTaskLocal(taskId, 'started'),
    });
    return item;
}

export async function wpFieldCompleteTask(form, taskId) {
    await window.wpAwaitPhotoUploads?.(form);
    const photos = wpCollectLocalPhotos(form);
    const note = form.querySelector('[data-wp-complete-note]')?.value || '';
    const payload = {
        task_id: Number(taskId),
        note,
        recorded_at: new Date().toISOString(),
        esg_value_numeric: form.querySelector('[data-wp-esg-numeric]')?.value || null,
        esg_value_boolean: form.querySelector('[data-wp-esg-boolean]')?.value || null,
        esg_value_string: form.querySelector('[data-wp-esg-string]')?.value || '',
        esg_value_json: form.querySelector('[data-wp-esg-json]')?.value || '',
        esg_value_multi_choice: Array.from(form.querySelectorAll('[data-wp-esg-multi]:checked')).map((el) => el.value),
    };

    return wpFieldEnqueue({
        type: 'task.complete',
        unitToken: unitToken(),
        payload,
        photos,
        onQueued: () => markTaskLocal(taskId, 'completed'),
    });
}

export async function wpFieldCreateIssue(form) {
    await window.wpAwaitPhotoUploads?.(form);
    const photos = wpCollectLocalPhotos(form);
    const description = form.querySelector('[data-wp-report-description]')?.value
        || form.querySelector('#description')?.value
        || '';

    return wpFieldEnqueue({
        type: 'issue.create',
        unitToken: unitToken(),
        payload: {
            description,
            original_language: document.documentElement.lang?.slice(0, 2) || 'nl',
        },
        photos,
    });
}

export async function wpFieldUnitCheck(payload) {
    return wpFieldEnqueue({
        type: 'unit.check',
        unitToken: unitToken(),
        payload,
    });
}

export async function wpFieldFlush() {
    try {
        const items = await allItems();
        for (const item of items) {
            await withStore('readwrite', (store) => store.delete(item.client_id));
        }
    } catch {
        // IndexedDB kan ontbreken (private mode); dan is er niets te wissen.
    }
    localStorage.removeItem('wp-field-outbox-identity');
    emitStatus(navigator.onLine ? 'online' : 'saved', { pending: 0 });
}

async function syncIdentity() {
    const identity = String(window.__wpFieldSync?.identity || '');
    const key = 'wp-field-outbox-identity';
    const stored = localStorage.getItem(key);
    if (stored === identity) {
        return;
    }
    if (stored) {
        await wpFieldFlush();
    }
    if (identity) {
        localStorage.setItem(key, identity);
    }
}

function bindPortal() {
    syncIdentity();
    window.addEventListener('online', () => wpFieldDrain());
    window.addEventListener('offline', async () => {
        const pending = (await allItems()).length;
        emitStatus('saved', { pending });
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            wpFieldDrain();
        }
    });
    document.addEventListener('livewire:commit', syncIdentity);
    document.addEventListener('livewire:navigated', syncIdentity);
    wpFieldDrain();
}

window.wpFieldEnqueue = wpFieldEnqueue;
window.wpFieldDrain = wpFieldDrain;
window.wpFieldFlush = wpFieldFlush;
window.wpFieldStartTask = wpFieldStartTask;
window.wpFieldCompleteTask = wpFieldCompleteTask;
window.wpFieldCreateIssue = wpFieldCreateIssue;
window.wpFieldUnitCheck = wpFieldUnitCheck;
window.wpCollectLocalPhotos = wpCollectLocalPhotos;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindPortal);
} else {
    bindPortal();
}
