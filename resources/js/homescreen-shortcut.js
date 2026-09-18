/**
 * Clock Point home-screen shortcut (only when the Clock Point flag is on).
 * Android Chrome: capture beforeinstallprompt and prompt on the WinProx icon.
 * iOS / others: let Livewire open the instruction modal.
 */

let deferredPrompt = null;

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
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
