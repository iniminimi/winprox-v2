@if ($unit->hasGps())
    @if (! empty($inline))
        <span class="wp-unit-title-row__sep" aria-hidden="true">-</span>
    @endif
    <x-wp-tooltip :text="__('locations.units.gps_history.open')">
        <button
            type="button"
            class="wp-muted wp-unit-gps-trigger"
            data-manual-capture-trigger="unit-gps-open"
            wire:click="$dispatch('open-unit-gps-history', { unitId: {{ $unit->id }} })"
            aria-label="{{ __('locations.units.gps_history.open') }}"
        >
            @include('partials.wp-gps-pin-icon', ['class' => 'wp-unit-gps-trigger__icon'])
            <span>GPS</span>
        </button>
    </x-wp-tooltip>
@endif
