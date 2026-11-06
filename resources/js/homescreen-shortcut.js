/**
 * Home-screen and desktop install shortcuts.
 * Clock Point: Android Chrome captures beforeinstallprompt on the WinProx icon.
 * Backoffice: the same prompt installs a desktop window in Chrome or Edge.
 * Each shortcut keeps its own installed flag so one app does not hide the other.
 */

let deferredPrompt = null;
let deferredBackofficePrompt = null;

const INSTALLED_KEY = 'wp-pwa-installed';
const BACKOFFICE_INSTALLED_KEY = 'wp-backoffice-pwa-installed';

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true;
}

function readFlag(key) {
    try {
        return window.localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function markInstalled(key, className) {
    try {
        window.localStorage.setItem(key, '1');
    } catch {
        // Private mode or storage denied: class-only fallback still applies.
    }
    document.documentElement.classList.add(className);
}

function markHomescreenInstalled() {
    markInstalled(INSTALLED_KEY, 'wp-pwa-installed');
}

function browserInstallsDesktopApp() {
    const ua = navigator.userAgent;
    if (/Firefox\//.test(ua)) {
        return false;
    }

    return /Edg\/|Chrome\/|Chromium\//.test(ua);
}

function backofficeInstallWrap() {
    return document.querySelector('.wp-backoffice-install');
}

function syncBackofficeInstallButton() {
    const show = Boolean(backofficeInstallWrap())
        && browserInstallsDesktopApp()
        && !isStandaloneDisplay()
        && !readFlag(BACKOFFICE_INSTALLED_KEY);
    document.documentElement.classList.toggle('wp-backoffice-pwa-available', show);
}

function markBackofficeInstalled() {
    markInstalled(BACKOFFICE_INSTALLED_KEY, 'wp-backoffice-pwa-installed');
    document.documentElement.classList.remove('wp-backoffice-pwa-available');
}

async function hideWhenRelatedAppInstalled(buttonSelector, mark) {
    if (!document.querySelector(buttonSelector)) {
        return;
    }

    if (typeof navigator.getInstalledRelatedApps !== 'function') {
        return;
    }

    try {
        const apps = await navigator.getInstalledRelatedApps();
        if (apps.some((app) => app.platform === 'webapp')) {
            mark();
        }
    } catch {
        // The browser may refuse the call outside a secure top-level page.
    }
}

async function hideShortcutWhenInstalled() {
    if (document.querySelector('[data-wp-homescreen-install]') && (isStandaloneDisplay() || readFlag(INSTALLED_KEY))) {
        markHomescreenInstalled();
    } else {
        await hideWhenRelatedAppInstalled('[data-wp-homescreen-install]', markHomescreenInstalled);
    }

    syncBackofficeInstallButton();

    if (!document.querySelector('[data-wp-backoffice-install]') || !browserInstallsDesktopApp()) {
        return;
    }

    if (isStandaloneDisplay() || readFlag(BACKOFFICE_INSTALLED_KEY)) {
        markBackofficeInstalled();
        return;
    }

    await hideWhenRelatedAppInstalled('[data-wp-backoffice-install]', markBackofficeInstalled);
}

window.addEventListener('beforeinstallprompt', (event) => {
    const homescreen = document.querySelector('[data-wp-homescreen-install]');
    const backoffice = document.querySelector('[data-wp-backoffice-install]');
    if (!homescreen && !backoffice) {
        return;
    }

    event.preventDefault();

    if (homescreen && !readFlag(INSTALLED_KEY)) {
        deferredPrompt = event;
    }
    if (backoffice && !readFlag(BACKOFFICE_INSTALLED_KEY)) {
        deferredBackofficePrompt = event;
    }
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-wp-homescreen-install]');
    if (!trigger || isStandaloneDisplay() || readFlag(INSTALLED_KEY) || !deferredPrompt) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const promptEvent = deferredPrompt;
    deferredPrompt = null;
    promptEvent.prompt();
    promptEvent.userChoice.then((choice) => {
        if (choice.outcome === 'accepted') {
            markHomescreenInstalled();
        }
    }).catch(() => {});
}, true);

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-wp-backoffice-install]');
    if (!trigger || isStandaloneDisplay() || readFlag(BACKOFFICE_INSTALLED_KEY) || !deferredBackofficePrompt) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    const promptEvent = deferredBackofficePrompt;
    deferredBackofficePrompt = null;
    promptEvent.prompt();
    promptEvent.userChoice.then((choice) => {
        if (choice.outcome === 'accepted') {
            markBackofficeInstalled();
        }
    }).catch(() => {});
}, true);

window.addEventListener('appinstalled', () => {
    if (document.querySelector('[data-wp-homescreen-install]')) {
        markHomescreenInstalled();
    }
    if (document.querySelector('[data-wp-backoffice-install]')) {
        markBackofficeInstalled();
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        hideShortcutWhenInstalled();
    }, { once: true });
} else {
    hideShortcutWhenInstalled();
}
