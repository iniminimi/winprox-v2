/**
 * Clock Point: meldingen op de gekoppelde gsm.
 * iPhone levert push alleen aan de app op het beginscherm.
 * De knop is de tik die de browser eist voor toestemming.
 */

let synced = false;
let decided = false;

function hidePushOffer() {
    document.documentElement.classList.remove('wp-push-offer');
}

function pushRoot() {
    return document.querySelector('[data-wp-push]');
}

function standaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true;
}

function iosDevice() {
    const ua = window.navigator.userAgent || '';
    return /iPad|iPhone|iPod/.test(ua)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
}

function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function canAsk() {
    return pushSupported() && (!iosDevice() || standaloneDisplay());
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

function offerPush() {
    const root = pushRoot();
    if (!root || !canAsk() || decided || Notification.permission !== 'default') {
        hidePushOffer();
        return;
    }
    document.documentElement.classList.add('wp-push-offer');
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
    hidePushOffer();
}

function refreshPush() {
    offerPush();
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
    const button = event.target instanceof Element ? event.target.closest('[data-wp-push-enable]') : null;
    const root = button?.closest('[data-wp-push]');
    if (!button || !root || !canAsk()) {
        return;
    }
    Notification.requestPermission().then((permission) => {
        if (permission === 'default') {
            return;
        }
        decided = true;
        hidePushOffer();
        if (permission !== 'granted') {
            return;
        }
        synced = true;
        subscribe(root).catch(() => {
            synced = false;
        });
    });
});

document.addEventListener('DOMContentLoaded', refreshPush);
document.addEventListener('livewire:navigated', refreshPush);
document.addEventListener('livewire:init', () => {
    if (window.Livewire?.hook) {
        window.Livewire.hook('morphed', () => refreshPush());
    }
});
