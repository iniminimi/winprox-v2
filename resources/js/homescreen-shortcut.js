/**
 * Clock Point home-screen shortcut.
 * Android Chrome: capture beforeinstallprompt and prompt on the WinProx icon.
 * Once that app is installed, hide the icon in the browser tab.
 * iOS / others: let Livewire open the instruction modal.
 */

let deferredPrompt = null;

const INSTALLED_KEY = 'wp-pwa-installed';

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function readInstalledFlag() {
    try {
        return window.localStorage.getItem(INSTALLED_KEY) === '1';
    } catch {
        return false;
    }
}

function markHomescreenInstalled() {
    try {
        window.localStorage.setItem(INSTALLED_KEY, '1');
    } catch {
        // Private mode or storage denied: class-only fallback still applies.
    }
    document.documentElement.classList.add('wp-pwa-installed');
}

async function hideShortcutWhenInstalled() {
    if (isStandaloneDisplay() || readInstalledFlag()) {
        markHomescreenInstalled();
        return;
    }

    if (typeof navigator.getInstalledRelatedApps !== 'function') {
        return;
    }

    try {
        const apps = await navigator.getInstalledRelatedApps();
        if (apps.some((app) => app.platform === 'webapp')) {
            markHomescreenInstalled();
        }
    } catch {
        // The browser may refuse the call outside a secure top-level page.
    }
}

window.addEventListener('beforeinstallprompt', (event) => {
    if (!document.querySelector('[data-wp-homescreen-install]')) {
        return;
    }

    event.preventDefault();
    if (readInstalledFlag()) {
        return;
    }
    deferredPrompt = event;
});

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-wp-homescreen-install]');
    if (!trigger || isStandaloneDisplay() || readInstalledFlag() || !deferredPrompt) {
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

window.addEventListener('appinstalled', markHomescreenInstalled);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        hideShortcutWhenInstalled();
    }, { once: true });
} else {
    hideShortcutWhenInstalled();
}
