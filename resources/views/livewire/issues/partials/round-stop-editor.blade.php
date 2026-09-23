@php
    $selectedIds = array_values(array_map('intval', $round_stop_unit_ids));
    $unitsById = $grouped->flatten(1)->keyBy('id');
    $allIds = $unitsById->keys()->map(fn ($id): int => (int) $id)->all();
    $allSelected = $allIds !== [] && array_diff($allIds, $selectedIds) === [];
    $selectedSet = array_fill_keys($selectedIds, true);
    $locationLabel = [];
    $locationSearch = [];
    $unitsByLocation = [];
    $isCompactSite = [];
    foreach ($grouped as $locationUnits) {
        $first = $locationUnits->first();
        $locationId = (int) ($first?->location_id ?? 0);
        $unitsByLocation[$locationId] = $locationUnits;
        $loc = $first?->location;
        $label = $loc?->name ?: ($loc?->address ?? __('issues.create.location_none'));
        $locationLabel[$locationId] = $label;
        $searchBits = [
            $label,
            (string) ($loc?->city ?? ''),
            (string) ($loc?->postal_code ?? ''),
            (string) ($loc?->street ?? ''),
        ];
        foreach ($locationUnits as $unit) {
            $searchBits[] = $unit->localizedName();
        }
        $locationSearch[$locationId] = mb_strtolower(trim(implode(' ', array_filter($searchBits))));
        $isCompactSite[$locationId] = $locationUnits->count() === 1
            && (bool) $locationUnits->first()?->is_site_unit;
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
    $catalogLocationIds = [];
    foreach ($unitsByLocation as $locationId => $locationUnits) {
        $hasUnselected = $locationUnits->contains(
            fn ($unit) => ! isset($selectedSet[(int) $unit->id])
        );
        if ($hasUnselected) {
            $catalogLocationIds[] = $locationId;
        }
    }
    $routeLocationCount = count($routeLocationIds);
    $selectedCount = count($selectedIds);
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
        x-data="{ q: '' }"
        aria-label="{{ __('issues.create.round_stops_order') }}"
    >
        <div class="wp-round-stop-picker__toolbar">
            <div class="wp-field wp-round-stop-picker__search">
                <label class="wp-label" for="{{ $pickerId }}_search">{{ __('issues.create.round_stops_search') }}</label>
                <input
                    type="search"
                    id="{{ $pickerId }}_search"
                    class="wp-input"
                    placeholder="{{ __('issues.create.round_stops_search_placeholder') }}"
                    x-model.debounce.150ms="q"
                    autocomplete="off"
                >
            </div>
            <label class="wp-round-stop-picker__select-all wp-check wp-text-sm">
                <input type="checkbox" @checked($allSelected) wire:click.prevent="toggleAllRoundStops">
                {{ __('issues.create.round_stops_select_all') }}
            </label>
        </div>

        <div class="wp-round-stop-picker__split">
            <section class="wp-round-stop-picker__panel wp-round-stop-picker__catalog" aria-label="{{ __('issues.create.round_stops_catalog') }}">
                <h3 class="wp-round-stop-picker__panel-title">{{ __('issues.create.round_stops_catalog') }}</h3>
                <div class="wp-round-stop-picker__body">
                    @forelse ($catalogLocationIds as $locationId)
                        @php
                            $locationUnits = $unitsByLocation[$locationId];
                            $compact = $isCompactSite[$locationId] ?? false;
                            $unselectedInLocation = $locationUnits->filter(
                                fn ($unit) => ! isset($selectedSet[(int) $unit->id])
                            );
                            $searchHaystack = e($locationSearch[$locationId] ?? '');
                        @endphp
                        <div
                            class="wp-round-stop-picker__group"
                            wire:key="{{ $pickerId }}-catalog-loc-{{ $locationId }}"
                            data-round-stop-search="{{ $searchHaystack }}"
                            x-show="!q.trim() || ($el.dataset.roundStopSearch || '').includes(q.trim().toLowerCase())"
                        >
                            @if ($compact)
                                @php $unit = $unselectedInLocation->first(); @endphp
                                @if ($unit)
                                    <label class="wp-round-stop-picker__row wp-round-stop-picker__row--site">
                                        <span class="wp-round-stops__handle" aria-hidden="true"></span>
                                        <input
                                            type="checkbox"
                                            value="{{ $unit->id }}"
                                            wire:click.prevent="toggleRoundStop({{ (int) $unit->id }})"
                                            data-round-stop
                                        >
                                        <span class="wp-round-stops__index" aria-hidden="true"></span>
                                        <span class="wp-round-stop-picker__name">{{ $locationLabel[$locationId] }}</span>
                                    </label>
                                @endif
                            @else
                                <div class="wp-round-stop-picker__group-label">
                                    <span>{{ $locationLabel[$locationId] }}</span>
                                </div>
                                @foreach ($unselectedInLocation as $unit)
                                    <label class="wp-round-stop-picker__row" wire:key="{{ $pickerId }}-catalog-unit-{{ $unit->id }}">
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
                            @endif
                        </div>
                    @empty
                        <p class="wp-muted wp-text-sm wp-round-stop-picker__empty">{{ __('issues.create.round_stops_catalog_empty') }}</p>
                    @endforelse
                </div>
            </section>

            <section
                class="wp-round-stop-picker__panel wp-round-stop-picker__route"
                aria-label="{{ __('issues.create.round_stops_route') }}"
                x-data="wpRoundStopSort"
                :class="{ 'wp-round-stops--sorting': from !== null }"
            >
                <h3 class="wp-round-stop-picker__panel-title">
                    {{ __('issues.create.round_stops_route') }}
                    <span class="wp-muted wp-text-sm">({{ $selectedCount }})</span>
                </h3>
                <div class="wp-round-stop-picker__body">
                    @if ($selectedCount === 0)
                        <p class="wp-muted wp-text-sm wp-round-stop-picker__empty">{{ __('issues.create.round_stops_route_empty') }}</p>
                    @else
                        @foreach ($routeLocationIds as $locationId)
                            @php
                                $locationUnits = $unitsByLocation[$locationId];
                                $compact = $isCompactSite[$locationId] ?? false;
                                $canDragLocation = $routeLocationCount > 1;
                                $selectedInLocation = [];
                                foreach ($selectedIds as $index => $stopUnitId) {
                                    $stopUnit = $unitsById->get($stopUnitId) ?? $unitsById->get((string) $stopUnitId);
                                    if ((int) ($stopUnit?->location_id ?? 0) !== (int) $locationId) {
                                        continue;
                                    }
                                    $selectedInLocation[] = ['index' => $index, 'unit' => $stopUnit];
                                }
                            @endphp
                            <div
                                class="wp-round-stop-picker__group"
                                wire:key="{{ $pickerId }}-route-loc-{{ $locationId }}"
                                data-round-stop-location="{{ $locationId }}"
                                :class="{
                                    'wp-round-stops__group--dragging': kind === 'location' && from === '{{ $locationId }}',
                                    'wp-round-stops__group--drop': kind === 'location' && over === '{{ $locationId }}' && from !== '{{ $locationId }}',
                                }"
                            >
                                @if ($compact)
                                    @php
                                        $item = $selectedInLocation[0] ?? null;
                                        $index = (int) ($item['index'] ?? 0);
                                        $unit = $item['unit'] ?? null;
                                    @endphp
                                    @if ($unit)
                                        <div
                                            class="wp-round-stop-picker__row wp-round-stop-picker__row--stop wp-round-stop-picker__row--site"
                                            wire:key="{{ $pickerId }}-route-unit-{{ $unit->id }}"
                                            data-round-stop-index="{{ $index }}"
                                            data-round-stop-location="{{ $locationId }}"
                                            title="{{ __('issues.create.round_stops_drag_location') }}"
                                            :class="{
                                                'wp-round-stops__item--dragging': kind === 'location' && from === '{{ $locationId }}',
                                                'wp-round-stops__item--drop': kind === 'location' && over === '{{ $locationId }}' && from !== '{{ $locationId }}',
                                            }"
                                            @if ($canDragLocation)
                                                @pointerdown="onLocationPointerDown($event, '{{ $locationId }}')"
                                                @pointermove="onPointerMove($event)"
                                                @pointerup="onPointerUp($event)"
                                                @pointercancel="onPointerUp($event)"
                                            @endif
                                        >
                                            <span class="wp-round-stops__handle" aria-hidden="true">
                                                @if ($canDragLocation)
                                                    <svg class="wp-round-stops__grip" viewBox="0 0 8 14" focusable="false">
                                                        <circle cx="2" cy="2" r="1.35"></circle>
                                                        <circle cx="6" cy="2" r="1.35"></circle>
                                                        <circle cx="2" cy="7" r="1.35"></circle>
                                                        <circle cx="6" cy="7" r="1.35"></circle>
                                                        <circle cx="2" cy="12" r="1.35"></circle>
                                                        <circle cx="6" cy="12" r="1.35"></circle>
                                                    </svg>
                                                @endif
                                            </span>
                                            <input
                                                type="checkbox"
                                                value="{{ $unit->id }}"
                                                checked
                                                wire:click.prevent="toggleRoundStop({{ (int) $unit->id }})"
                                                @pointerdown.stop
                                            >
                                            <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                                            <span class="wp-round-stop-picker__name">{{ $locationLabel[$locationId] }}</span>
                                        </div>
                                    @endif
                                @else
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
                                            wire:key="{{ $pickerId }}-route-unit-{{ $unit->id }}"
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
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </section>
        </div>
    </div>
    @if ($hiddenCount > 0)
        <p class="wp-muted wp-text-sm">{{ trans_choice('issues.create.round_stops_hidden', $hiddenCount, ['count' => $hiddenCount]) }}</p>
    @endif
    @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
    @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
@endif
