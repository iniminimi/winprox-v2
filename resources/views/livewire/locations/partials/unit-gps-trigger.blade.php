@if ($unit->hasGps())
    @if (! empty($inline))
        <span class="wp-unit-title-row__sep" aria-hidden="true">-</span>
    @endif
    <span class="wp-tooltip">
        <button
            type="button"
            class="wp-muted wp-unit-gps-trigger"
            wire:click="$dispatch('open-unit-gps-history', { unitId: {{ $unit->id }} })"
            aria-label="{{ __('locations.units.gps_history.open') }}"
        >
            @include('partials.wp-gps-pin-icon', ['class' => 'wp-unit-gps-trigger__icon'])
            <span>GPS</span>
        </button>
        <span class="wp-tooltip__bubble" role="tooltip">{{ __('locations.units.gps_history.open') }}</span>
    </span>
@endif
