<button type="button" class="wp-kpi wp-kpi--units" wire:click="$dispatch('open-add-device')">
    <div class="wp-kpi-body">
        <span class="wp-kpi-icon" aria-hidden="true">
            <x-wp-icon name="key" />
        </span>
        <div class="wp-kpi-main">
            <p class="wp-kpi-kicker">{{ __('dashboard.add_device.title') }}</p>
            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __('dashboard.add_device.body') }}</p>
        </div>
    </div>
</button>
