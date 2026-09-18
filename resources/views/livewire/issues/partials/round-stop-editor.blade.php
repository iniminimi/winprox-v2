@php
    $selectedIds = array_values(array_map('intval', $round_stop_unit_ids));
    $unitsById = $grouped->flatten(1)->keyBy('id');
    $allIds = $unitsById->keys()->map(fn ($id): int => (int) $id)->all();
    $allSelected = $allIds !== [] && array_diff($allIds, $selectedIds) === [];
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
            class="wp-round-stops"
            x-data="wpRoundStopSort"
            :class="{ 'wp-round-stops--sorting': from !== null }"
            aria-label="{{ __('issues.create.round_stops_order') }}"
        >
            @foreach ($selectedIds as $index => $stopUnitId)
                @php
                    $stopUnit = $unitsById->get($stopUnitId) ?? $unitsById->get((string) $stopUnitId);
                    $stopUnitName = $stopUnit?->localizedName() ?? ('#'.$stopUnitId);
                    $stopLocationName = $stopUnit?->location?->name
                        ?: ($stopUnit?->location?->address ?? null);
                    $stopLabel = $stopLocationName
                        ? $stopLocationName.' · '.$stopUnitName
                        : $stopUnitName;
                    $isFirst = $index === 0;
                    $isLast = $index === count($selectedIds) - 1;
                @endphp
                <li
                    class="wp-round-stops__item wp-round-stops__item--route"
                    wire:key="{{ $pickerId }}-order-{{ $stopUnitId }}"
                    data-round-stop-index="{{ $index }}"
                    :class="{
                        'wp-round-stops__item--dragging': from === {{ $index }},
                        'wp-round-stops__item--drop': over === {{ $index }} && from !== null && from !== {{ $index }},
                    }"
                >
                    <span
                        class="wp-round-stops__handle"
                        aria-label="{{ __('issues.create.round_stops_drag') }}"
                        title="{{ __('issues.create.round_stops_drag') }}"
                        @pointerdown="onPointerDown($event, {{ $index }})"
                        @pointermove="onPointerMove($event)"
                        @pointerup="onPointerUp($event)"
                        @pointercancel="onPointerUp($event)"
                    ></span>
                    <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                    <span class="wp-round-stops__name">{{ $stopLabel }}</span>
                    <span class="wp-round-stops__move">
                        @if ($isFirst)
                            <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('issues.create.round_stops_move_up') }}">‹</span>
                        @else
                            <button type="button" class="wp-pagination__control" wire:click="moveRoundStop({{ $index }}, -1)" aria-label="{{ __('issues.create.round_stops_move_up') }}">‹</button>
                        @endif
                        @if ($isLast)
                            <span class="wp-pagination__control" aria-disabled="true" aria-label="{{ __('issues.create.round_stops_move_down') }}">›</span>
                        @else
                            <button type="button" class="wp-pagination__control" wire:click="moveRoundStop({{ $index }}, 1)" aria-label="{{ __('issues.create.round_stops_move_down') }}">›</button>
                        @endif
                    </span>
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
