/**
 * Clock Point: meldingen op de gekoppelde gsm.
 * Alleen in de app vanaf het startscherm.
 * default → knop aanzetten; granted → abonneren en weg; denied → herstelpad.
 */

const LEGACY_DECIDED_KEY = 'wp-push-decided';
const DECLINED_KEY = 'wp-push-declined';

let synced = false;

function pushRoot() {
    return document.querySelector('[data-wp-push]');
}

function helpPanel() {
    return document.querySelector('[data-wp-push-help]');
}

function standaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.matchMedia('(display-mode: minimal-ui)').matches
        || window.navigator.standalone === true;
}

function syncStandaloneClass() {
    document.documentElement.classList.toggle('wp-pwa-standalone', standaloneDisplay());
}

function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function canAsk() {
    return pushSupported() && standaloneDisplay();
}

function clearLegacyDecided() {
    try {
        window.localStorage.removeItem(LEGACY_DECIDED_KEY);
    } catch {
        // Private mode.
    }
}

function readDeclined() {
    try {
        return window.localStorage.getItem(DECLINED_KEY) === '1';
    } catch {
        return false;
    }
}

function writeDeclined(on) {
    try {
        if (on) {
            window.localStorage.setItem(DECLINED_KEY, '1');
        } else {
            window.localStorage.removeItem(DECLINED_KEY);
        }
    } catch {
        // Private mode.
    }
    document.documentElement.classList.toggle('wp-push-declined', on);
}

function setPushState(state) {
    const root = document.documentElement;
    root.classList.toggle('wp-push-offer', state === 'offer');
    root.classList.toggle('wp-push-asking', state === 'asking');
    root.classList.toggle('wp-push-blocked', state === 'blocked');
    root.classList.toggle('wp-push-ready', state === 'ready');
}

function openBlockedHelp() {
    const panel = helpPanel();
    if (!panel) {
        return;
    }
    panel.hidden = false;
    panel.setAttribute('aria-hidden', 'false');
}

function closeBlockedHelp() {
    const panel = helpPanel();
    if (!panel) {
        return;
    }
    panel.hidden = true;
    panel.setAttribute('aria-hidden', 'true');
}

function syncPushUi() {
    syncStandaloneClass();
    clearLegacyDecided();

    if (!canAsk()) {
        setPushState('idle');
        closeBlockedHelp();
        return;
    }

    const permission = Notification.permission;
    if (permission === 'granted') {
        setPushState('ready');
        closeBlockedHelp();
        return;
    }
    if (permission === 'denied') {
        setPushState('blocked');
        return;
    }
    setPushState('offer');
}

function urlBase64ToUint8Array(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let index = 0; index < raw.length; index += 1) {
        output[index] = raw.charCodeAt(index);
    }
    return output;
}

async function storeSubscription(root, subscription) {
    const json = subscription.toJSON();
    const keys = json.keys || {};
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const response = await fetch(root.dataset.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({
            endpoint: subscription.endpoint,
            public_key: keys.p256dh || '',
            auth_token: keys.auth || '',
            locale: (root.dataset.locale || 'nl').slice(0, 2).toLowerCase(),
        }),
    });
    if (!response.ok) {
        throw new Error('push');
    }
}

async function subscribe(root) {
    const registration = await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(root.dataset.publicKey || ''),
        });
    }
    await storeSubscription(root, subscription);
    writeDeclined(false);
    setPushState('ready');
    closeBlockedHelp();
}

function refreshPush() {
    syncPushUi();
    const root = pushRoot();
    if (synced || !root || !canAsk() || Notification.permission !== 'granted') {
        return;
    }
    synced = true;
    subscribe(root).catch(() => {
        synced = false;
    });
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
        return;
    }

    if (target.closest('[data-wp-push-help-close]')) {
        closeBlockedHelp();
        return;
    }

    if (target.closest('[data-wp-push-retry]')) {
        synced = false;
        refreshPush();
        if (Notification.permission === 'denied') {
            openBlockedHelp();
        }
        return;
    }

    if (target.closest('[data-wp-push-decline]')) {
        writeDeclined(true);
        closeBlockedHelp();
        return;
    }

    if (target.closest('[data-wp-push-blocked]')) {
        openBlockedHelp();
        return;
    }

    const button = target.closest('[data-wp-push-enable]');
    const root = button?.closest('[data-wp-push]') || pushRoot();
    if (!button || !root || !canAsk()) {
        return;
    }
    if (Notification.permission === 'denied') {
        setPushState('blocked');
        openBlockedHelp();
        return;
    }
    if (Notification.permission === 'granted') {
        setPushState('ready');
        synced = true;
        subscribe(root).catch(() => {
            synced = false;
        });
        return;
    }

    setPushState('asking');
    Notification.requestPermission().then((permission) => {
        if (permission === 'granted') {
            setPushState('ready');
            synced = true;
            subscribe(root).catch(() => {
                synced = false;
            });
            return;
        }
        if (permission === 'denied') {
            setPushState('blocked');
            openBlockedHelp();
            return;
        }
        setPushState('offer');
    });
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        synced = false;
        refreshPush();
    }
});
window.addEventListener('focus', () => {
    synced = false;
    refreshPush();
});

clearLegacyDecided();
writeDeclined(readDeclined());
syncStandaloneClass();
['standalone', 'fullscreen', 'minimal-ui'].forEach((mode) => {
    window.matchMedia(`(display-mode: ${mode})`).addEventListener('change', refreshPush);
});
document.addEventListener('wp-homescreen-installed', refreshPush);
document.addEventListener('DOMContentLoaded', refreshPush);
document.addEventListener('livewire:navigated', refreshPush);
document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', () => refreshPush());
    }
});
