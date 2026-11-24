{{-- Startscherm-snelkoppeling voor WinProx OPS (aparte PWA). --}}
<button
    type="button"
    class="wp-homescreen-shortcut-wrap"
    data-wp-ops-homescreen-install
    wire:click="openHomescreenHelp"
>
    <span class="wp-homescreen-shortcut__label">{{ __('ops.homescreen.title') }}</span>
    <span class="wp-homescreen-shortcut">
        <img
            src="{{ asset('images/pwa/winprox-192.png') }}"
            alt=""
            width="40"
            height="40"
        >
        <span class="wp-homescreen-shortcut__add" aria-hidden="true">
            <x-wp-icon name="plus" />
        </span>
    </span>
</button>
