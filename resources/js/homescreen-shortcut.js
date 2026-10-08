/**
 * Clock Point home-screen shortcut.
 * Android Chrome: capture beforeinstallprompt and prompt on the WinProx icon.
 * Once that app is installed, hide the icon in the browser tab.
 * iOS / others: let Livewire open the instruction modal.
 */

let deferredPrompt = null;

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function markHomescreenInstalled() {
    document.documentElement.classList.add('wp-pwa-installed');
}

async function hideShortcutWhenInstalled() {
    if (isStandaloneDisplay()) {
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
    deferredPrompt = event;
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
}, true);

window.addEventListener('appinstalled', markHomescreenInstalled);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        hideShortcutWhenInstalled();
    }, { once: true });
} else {
    hideShortcutWhenInstalled();
}
