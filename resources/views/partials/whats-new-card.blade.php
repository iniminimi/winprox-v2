<a href="{{ route('whats-new.index') }}" class="wp-kpi wp-kpi--units" wire:key="whats-new-card">
    <div class="wp-kpi-body">
        <span class="wp-kpi-icon" aria-hidden="true">
            <x-wp-icon name="information-circle" />
        </span>
        <div class="wp-kpi-main">
            <p class="wp-kpi-kicker">{{ __('whats_new.card_title') }}</p>
            <p class="wp-kpi-meta wp-kpi-meta--wrap">{{ __('whats_new.card_body') }}</p>
        </div>
    </div>
</a>
