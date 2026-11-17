{{-- Startscherm-snelkoppeling op het Clock Point-portaal. --}}
<button
    type="button"
    class="wp-homescreen-shortcut-wrap"
    data-wp-homescreen-install
    wire:click="openHomescreenHelp"
>
    <span class="wp-homescreen-shortcut__label">{{ __('time.portal.homescreen.title') }}</span>
    <span class="wp-homescreen-shortcut">
        <img
            src="{{ asset('images/Winprox_logo_100.png') }}"
            alt=""
            width="40"
            height="40"
        >
        <span class="wp-homescreen-shortcut__add" aria-hidden="true">
            <x-wp-icon name="plus" />
        </span>
    </span>
</button>
