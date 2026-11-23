/**
 * Home-screen and desktop install shortcuts.
 * Clock Point: Android Chrome captures beforeinstallprompt on the WinProx icon.
 * Backoffice: the same prompt installs a desktop window in Chrome or Edge.
 * Each shortcut keeps its own installed flag so one app does not hide the other.
 */

let deferredPrompt = null;
let deferredBackofficePrompt = null;
let homescreenCanInstall = false;
let backofficeCanInstall = false;

const INSTALLED_KEY = 'wp-pwa-installed';
const BACKOFFICE_INSTALLED_KEY = 'wp-backoffice-pwa-installed';

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: minimal-ui)').matches
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

function notifyHomescreenInstall() {
    document.dispatchEvent(new Event('wp-homescreen-installed'));
}

function markHomescreenInstalled() {
    markInstalled(INSTALLED_KEY, 'wp-pwa-installed');
    notifyHomescreenInstall();
}

function showOpenAppScreen() {
    if (isStandaloneDisplay()) {
        return;
    }
    document.documentElement.classList.add('wp-pwa-open-app');
    document.querySelector('.wp-pwa-open-screen')?.removeAttribute('hidden');
}

function dismissOpenAppScreen() {
    document.documentElement.classList.remove('wp-pwa-open-app');
    document.querySelector('.wp-pwa-open-screen')?.setAttribute('hidden', '');
}

function clearInstalled(key, className) {
    try {
        window.localStorage.removeItem(key);
    } catch {
        // Private mode: the class is enough for this page.
    }
    document.documentElement.classList.remove(className);
    if (key === INSTALLED_KEY) {
        notifyHomescreenInstall();
    }
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

async function relatedWebAppInstalled() {
    if (typeof navigator.getInstalledRelatedApps !== 'function') {
        return false;
    }

    try {
        const apps = await navigator.getInstalledRelatedApps();

        return apps.some((app) => app.platform === 'webapp');
    } catch {
        return false;
    }
}

async function hideShortcutWhenInstalled() {
    const homescreen = document.querySelector('[data-wp-homescreen-install]');
    if (homescreen) {
        if (isStandaloneDisplay()) {
            markHomescreenInstalled();
        } else if (homescreenCanInstall) {
            clearInstalled(INSTALLED_KEY, 'wp-pwa-installed');
        } else if (await relatedWebAppInstalled() && !homescreenCanInstall) {
            markHomescreenInstalled();
        } else {
            clearInstalled(INSTALLED_KEY, 'wp-pwa-installed');
        }
    }

    syncBackofficeInstallButton();

    if (!document.querySelector('[data-wp-backoffice-install]') || !browserInstallsDesktopApp()) {
        return;
    }

    if (isStandaloneDisplay()) {
        markBackofficeInstalled();
        return;
    }

    if (backofficeCanInstall) {
        clearInstalled(BACKOFFICE_INSTALLED_KEY, 'wp-backoffice-pwa-installed');
        syncBackofficeInstallButton();
        return;
    }

    if (await relatedWebAppInstalled() && !backofficeCanInstall) {
        markBackofficeInstalled();
        return;
    }

    clearInstalled(BACKOFFICE_INSTALLED_KEY, 'wp-backoffice-pwa-installed');
    syncBackofficeInstallButton();
}

window.addEventListener('beforeinstallprompt', (event) => {
    const homescreen = document.querySelector('[data-wp-homescreen-install]');
    const backoffice = document.querySelector('[data-wp-backoffice-install]');
    if (!homescreen && !backoffice) {
        return;
    }

    event.preventDefault();

    if (homescreen && !isStandaloneDisplay()) {
        homescreenCanInstall = true;
        clearInstalled(INSTALLED_KEY, 'wp-pwa-installed');
        deferredPrompt = event;
    }
    if (backoffice && !isStandaloneDisplay()) {
        backofficeCanInstall = true;
        clearInstalled(BACKOFFICE_INSTALLED_KEY, 'wp-backoffice-pwa-installed');
        deferredBackofficePrompt = event;
        syncBackofficeInstallButton();
    }
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-wp-homescreen-install]');
    if (!trigger || isStandaloneDisplay() || !deferredPrompt) {
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
            showOpenAppScreen();
        }
    }).catch(() => {});
}, true);

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-wp-pwa-open-dismiss]')) {
        event.preventDefault();
        dismissOpenAppScreen();
        return;
    }

    const trigger = event.target.closest('[data-wp-backoffice-install]');
    if (!trigger || isStandaloneDisplay() || !deferredBackofficePrompt) {
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
        showOpenAppScreen();
    }
    if (document.querySelector('[data-wp-backoffice-install]')) {
        markBackofficeInstalled();
    }
});

function bindPwaNav() {
    const bar = document.querySelector('[data-wp-pwa-nav]');
    if (!bar || bar.dataset.bound === '1') {
        return;
    }
    bar.dataset.bound = '1';

    bar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-wp-pwa]');
        if (!button || button.disabled) {
            return;
        }
        const action = button.getAttribute('data-wp-pwa');
        if (action === 'back') {
            history.back();
        } else if (action === 'forward') {
            history.forward();
        } else if (action === 'reload') {
            window.location.reload();
        }
    });

    const sync = () => {
        if (!window.navigation) {
            return;
        }
        const back = bar.querySelector('[data-wp-pwa="back"]');
        const forward = bar.querySelector('[data-wp-pwa="forward"]');
        if (back) {
            back.disabled = window.navigation.canGoBack === false;
        }
        if (forward) {
            forward.disabled = window.navigation.canGoForward === false;
        }
    };

    if (window.navigation) {
        window.navigation.addEventListener('currententrychange', sync);
        window.navigation.addEventListener('navigate', () => {
            window.queueMicrotask(sync);
        });
    }
    sync();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        hideShortcutWhenInstalled();
        bindPwaNav();
    }, { once: true });
} else {
    hideShortcutWhenInstalled();
    bindPwaNav();
}
