@php
    $selectedIds = array_values(array_map('intval', $round_stop_unit_ids));
    $unitsById = $grouped->flatten(1)->keyBy('id');
    $allIds = $unitsById->keys()->map(fn ($id): int => (int) $id)->all();
    $allSelected = $allIds !== [] && array_diff($allIds, $selectedIds) === [];
    $routeGroups = [];
    $currentLocationKey = null;
    foreach ($selectedIds as $index => $stopUnitId) {
        $stopUnit = $unitsById->get($stopUnitId) ?? $unitsById->get((string) $stopUnitId);
        $locationId = (int) ($stopUnit?->location_id ?? 0);
        $locationName = $stopUnit?->location?->name
            ?: ($stopUnit?->location?->address ?? __('issues.create.location_none'));
        if ($currentLocationKey === null || $currentLocationKey !== $locationId) {
            $routeGroups[] = [
                'location_id' => $locationId,
                'label' => $locationName,
                'items' => [],
            ];
            $currentLocationKey = $locationId;
        }
        $routeGroups[array_key_last($routeGroups)]['items'][] = [
            'index' => $index,
            'unit_id' => $stopUnitId,
            'name' => $stopUnit?->localizedName() ?? ('#'.$stopUnitId),
        ];
    }
    $locationCount = count($routeGroups);
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
    @if ($selectedIds !== [])
        <ol
            class="wp-round-stops wp-round-stops--route"
            x-data="wpRoundStopSort"
            :class="{ 'wp-round-stops--sorting': from !== null }"
            aria-label="{{ __('issues.create.round_stops_order') }}"
        >
            @foreach ($routeGroups as $routeGroup)
                @php
                    $locationId = (int) $routeGroup['location_id'];
                    $groupItems = $routeGroup['items'];
                @endphp
                <li
                    class="wp-round-stops__group"
                    wire:key="{{ $pickerId }}-loc-{{ $locationId }}"
                    data-round-stop-location="{{ $locationId }}"
                    :class="{
                        'wp-round-stops__group--dragging': kind === 'location' && from === '{{ $locationId }}',
                        'wp-round-stops__group--drop': kind === 'location' && over === '{{ $locationId }}' && from !== '{{ $locationId }}',
                    }"
                >
                    <div
                        class="wp-round-stops__group-head{{ $locationCount > 1 ? ' wp-round-stops__group-head--drag' : '' }}"
                        @if ($locationCount > 1)
                            title="{{ __('issues.create.round_stops_drag_location') }}"
                            @pointerdown="onLocationPointerDown($event, '{{ $locationId }}')"
                            @pointermove="onPointerMove($event)"
                            @pointerup="onPointerUp($event)"
                            @pointercancel="onPointerUp($event)"
                        @endif
                    >
                        @if ($locationCount > 1)
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
                        <span class="wp-round-stops__group-label">{{ $routeGroup['label'] }}</span>
                    </div>
                    <ol class="wp-round-stops wp-round-stops--units">
                        @foreach ($groupItems as $item)
                            @php $index = (int) $item['index']; @endphp
                            <li
                                class="wp-round-stops__item wp-round-stops__item--route"
                                wire:key="{{ $pickerId }}-order-{{ $item['unit_id'] }}"
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
                                <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                                <span class="wp-round-stops__name">{{ $item['name'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </li>
            @endforeach
        </ol>
        <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_order_help') }}</p>
    @endif
    <div class="wp-stack-tight">
        <label class="wp-check wp-text-sm">
            <input type="checkbox" @checked($allSelected) wire:click.prevent="toggleAllRoundStops">
            {{ __('issues.create.round_stops_select_all') }}
        </label>
        <div id="{{ $pickerId }}" class="wp-round-stop-picker">
            @foreach ($grouped as $locationUnits)
                @php
                    $groupLocation = $locationUnits->first()?->location;
                    $groupLabel = $groupLocation?->name ?: ($groupLocation?->address ?? __('issues.create.location_none'));
                @endphp
                <div class="wp-round-stop-picker__group" role="group" aria-label="{{ $groupLabel }}">
                    <p class="wp-round-stop-picker__group-label">{{ $groupLabel }}</p>
                    @foreach ($locationUnits as $unit)
                        <label class="wp-round-stop-picker__row" wire:key="{{ $pickerId }}-unit-{{ $unit->id }}">
                            <input
                                type="checkbox"
                                value="{{ $unit->id }}"
                                @checked(in_array((int) $unit->id, $selectedIds, true))
                                wire:click.prevent="toggleRoundStop({{ (int) $unit->id }})"
                                data-round-stop
                            >
                            <span>{{ $unit->localizedName() }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    <p class="wp-muted wp-text-sm">{{ __('issues.create.round_stops_select_help') }}</p>
    @if ($hiddenCount > 0)
        <p class="wp-muted wp-text-sm">{{ trans_choice('issues.create.round_stops_hidden', $hiddenCount, ['count' => $hiddenCount]) }}</p>
    @endif
    @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
    @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
@endif
