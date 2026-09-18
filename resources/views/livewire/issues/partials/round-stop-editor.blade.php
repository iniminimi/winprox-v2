@php
    $selectedIds = array_values(array_map('intval', $round_stop_unit_ids));
    $unitsById = $grouped->flatten(1)->keyBy('id');
    $allIds = $unitsById->keys()->map(fn ($id): int => (int) $id)->all();
    $allSelected = $allIds !== [] && array_diff($allIds, $selectedIds) === [];
    $selectedSet = array_fill_keys($selectedIds, true);
    $locationLabel = [];
    $unitsByLocation = [];
    foreach ($grouped as $locationUnits) {
        $first = $locationUnits->first();
        $locationId = (int) ($first?->location_id ?? 0);
        $unitsByLocation[$locationId] = $locationUnits;
        $locationLabel[$locationId] = $first?->location?->name
            ?: ($first?->location?->address ?? __('issues.create.location_none'));
    }
    $routeLocationIds = [];
    $seenLocations = [];
    foreach ($selectedIds as $stopUnitId) {
        $stopUnit = $unitsById->get($stopUnitId) ?? $unitsById->get((string) $stopUnitId);
        $locationId = (int) ($stopUnit?->location_id ?? 0);
        if (isset($seenLocations[$locationId])) {
            continue;
        }
        $routeLocationIds[] = $locationId;
        $seenLocations[$locationId] = true;
    }
    $idleLocationIds = [];
    foreach ($unitsByLocation as $locationId => $_units) {
        if (! isset($seenLocations[$locationId])) {
            $idleLocationIds[] = $locationId;
        }
    }
    $locationIds = array_merge($routeLocationIds, $idleLocationIds);
    $routeLocationCount = count($routeLocationIds);
@endphp

@if ($grouped->flatten(1)->isEmpty())
    <p class="wp-muted wp-text-sm">
        @if ($hiddenCount > 0)
            {{ trans_choice('issues.create.round_stops_unavailable', $hiddenCount, ['count' => $hiddenCount]) }}
        @else
            {{ __('issues.create.round_stops_empty') }}
        @endif
    </p>
@else
    <p class="wp-muted wp-text-sm">{{ $help }}</p>
    <div
        id="{{ $pickerId }}"
        class="wp-round-stop-picker"
        x-data="wpRoundStopSort"
        :class="{ 'wp-round-stops--sorting': from !== null }"
        aria-label="{{ __('issues.create.round_stops_order') }}"
    >
        <label class="wp-round-stop-picker__select-all wp-check wp-text-sm">
            <input type="checkbox" @checked($allSelected) wire:click.prevent="toggleAllRoundStops">
            {{ __('issues.create.round_stops_select_all') }}
        </label>
        <div class="wp-round-stop-picker__body">
            @foreach ($locationIds as $locationId)
                @php
                    $locationUnits = $unitsByLocation[$locationId];
                    $inRoute = isset($seenLocations[$locationId]);
                    $canDragLocation = $inRoute && $routeLocationCount > 1;
                    $selectedInLocation = [];
                    foreach ($selectedIds as $index => $stopUnitId) {
                        $stopUnit = $unitsById->get($stopUnitId) ?? $unitsById->get((string) $stopUnitId);
                        if ((int) ($stopUnit?->location_id ?? 0) !== (int) $locationId) {
                            continue;
                        }
                        $selectedInLocation[] = ['index' => $index, 'unit' => $stopUnit];
                    }
                    $unselectedInLocation = $locationUnits->filter(
                        fn ($unit) => ! isset($selectedSet[(int) $unit->id])
                    );
                @endphp
                <div
                    class="wp-round-stop-picker__group"
                    wire:key="{{ $pickerId }}-loc-{{ $locationId }}"
                    @if ($inRoute) data-round-stop-location="{{ $locationId }}" @endif
                    :class="{
                        'wp-round-stops__group--dragging': kind === 'location' && from === '{{ $locationId }}',
                        'wp-round-stops__group--drop': kind === 'location' && over === '{{ $locationId }}' && from !== '{{ $locationId }}',
                    }"
                >
                    <div
                        class="wp-round-stop-picker__group-label{{ $canDragLocation ? ' wp-round-stop-picker__group-label--drag' : '' }}"
                        @if ($canDragLocation)
                            title="{{ __('issues.create.round_stops_drag_location') }}"
                            @pointerdown="onLocationPointerDown($event, '{{ $locationId }}')"
                            @pointermove="onPointerMove($event)"
                            @pointerup="onPointerUp($event)"
                            @pointercancel="onPointerUp($event)"
                        @endif
                    >
                        @if ($canDragLocation)
                            <span class="wp-round-stops__handle" aria-hidden="true">
                                <svg class="wp-round-stops__grip" viewBox="0 0 8 14" focusable="false">
                                    <circle cx="2" cy="2" r="1.35"></circle>
                                    <circle cx="6" cy="2" r="1.35"></circle>
                                    <circle cx="2" cy="7" r="1.35"></circle>
                                    <circle cx="6" cy="7" r="1.35"></circle>
                                    <circle cx="2" cy="12" r="1.35"></circle>
                                    <circle cx="6" cy="12" r="1.35"></circle>
                                </svg>
                            </span>
                        @endif
                        <span>{{ $locationLabel[$locationId] }}</span>
                    </div>
                    @foreach ($selectedInLocation as $item)
                        @php
                            $index = (int) $item['index'];
                            $unit = $item['unit'];
                        @endphp
                        <div
                            class="wp-round-stop-picker__row wp-round-stop-picker__row--stop"
                            wire:key="{{ $pickerId }}-unit-{{ $unit->id }}"
                            data-round-stop-index="{{ $index }}"
                            data-round-stop-location="{{ $locationId }}"
                            title="{{ __('issues.create.round_stops_drag') }}"
                            :class="{
                                'wp-round-stops__item--dragging': kind === 'unit' && from === {{ $index }},
                                'wp-round-stops__item--drop': kind === 'unit' && over === {{ $index }} && from !== {{ $index }},
                            }"
                            @pointerdown.stop="onPointerDown($event, {{ $index }}, '{{ $locationId }}')"
                            @pointermove="onPointerMove($event)"
                            @pointerup="onPointerUp($event)"
                            @pointercancel="onPointerUp($event)"
                        >
                            <span class="wp-round-stops__handle" aria-hidden="true">
                                <svg class="wp-round-stops__grip" viewBox="0 0 8 14" focusable="false">
                                    <circle cx="2" cy="2" r="1.35"></circle>
                                    <circle cx="6" cy="2" r="1.35"></circle>
                                    <circle cx="2" cy="7" r="1.35"></circle>
                                    <circle cx="6" cy="7" r="1.35"></circle>
                                    <circle cx="2" cy="12" r="1.35"></circle>
                                    <circle cx="6" cy="12" r="1.35"></circle>
                                </svg>
                            </span>
                            <input
                                type="checkbox"
                                value="{{ $unit->id }}"
                                checked
                                wire:click.prevent="toggleRoundStop({{ (int) $unit->id }})"
                                @pointerdown.stop
                            >
                            <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                            <span class="wp-round-stop-picker__name">{{ $unit->localizedName() }}</span>
                        </div>
                    @endforeach
                    @foreach ($unselectedInLocation as $unit)
                        <label class="wp-round-stop-picker__row" wire:key="{{ $pickerId }}-unit-{{ $unit->id }}">
                            <span class="wp-round-stops__handle" aria-hidden="true"></span>
                            <input
                                type="checkbox"
                                value="{{ $unit->id }}"
                                wire:click.prevent="toggleRoundStop({{ (int) $unit->id }})"
                                data-round-stop
                            >
                            <span class="wp-round-stops__index" aria-hidden="true"></span>
                            <span class="wp-round-stop-picker__name">{{ $unit->localizedName() }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    @if ($hiddenCount > 0)
        <p class="wp-muted wp-text-sm">{{ trans_choice('issues.create.round_stops_hidden', $hiddenCount, ['count' => $hiddenCount]) }}</p>
    @endif
    @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
    @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
@endif
