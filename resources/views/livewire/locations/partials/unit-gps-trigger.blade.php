@if ($unit->hasGps())
    @if (! empty($inline))
        <span class="wp-unit-title-row__sep" aria-hidden="true"> - </span>
    @endif
    <button
        type="button"
        class="wp-muted wp-unit-gps-trigger"
        wire:click="$dispatch('open-unit-gps-history', { unitId: {{ $unit->id }} })"
        title="{{ __('locations.units.gps_history.open') }}"
    >
        <svg class="wp-unit-gps-trigger__icon" fill="#EA4335" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg>
        <span>GPS</span>
    </button>
@endif
