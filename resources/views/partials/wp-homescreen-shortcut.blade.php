{{-- Clock Point-snelkoppeling (alleen als het vinkje aan staat). --}}
<x-wp-tooltip class="wp-homescreen-shortcut-wrap wp-tooltip--end" :text="__('time.portal.homescreen.title')" wrap>
    <button
        type="button"
        class="wp-homescreen-shortcut"
        data-wp-homescreen-install
        wire:click="openHomescreenHelp"
        title="{{ __('time.portal.homescreen.title') }}"
        aria-label="{{ __('time.portal.homescreen.title') }}"
    >
        <img
            src="{{ asset('images/Winprox_logo_100.png') }}"
            alt=""
            width="40"
            height="40"
        >
    </button>
</x-wp-tooltip>
