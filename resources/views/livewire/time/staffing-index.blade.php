<div class="wp-stack" data-manual-capture="time-staffing">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="time"
                :title="__('time.staffing.title')"
                help-page="time.staffing"
                :subtitle="__('time.staffing.subtitle')"
            />
        </div>
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif
    @error('staffingRows') <p class="wp-error">{{ $message }}</p> @enderror

    @if ($staffingLocations->isEmpty())
        <div class="wp-card wp-card-pad">
            <p class="wp-hint">{{ __('time.staffing.empty_locations') }}</p>
        </div>
    @else
        <div class="wp-card wp-filter-panel">
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="staffing-location">{{ __('time.filters.location') }}</label>
                <select id="staffing-location" class="wp-select" wire:model.live="selectedLocationId">
                    @foreach ($staffingLocations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($selectedLocation !== null)
            <div class="wp-card wp-card-pad wp-stack" wire:key="staffing-location-{{ $selectedLocation->id }}">
                <div class="wp-stack-tight">
                    @foreach ($staffingRows[$selectedLocation->id] ?? [] as $index => $row)
                        <div class="wp-cluster wp-cluster--tight" wire:key="staffing-row-{{ $selectedLocation->id }}-{{ $index }}">
                            @if ($row['stale_label'] !== null)
                                <select class="wp-select wp-select--compact" disabled aria-label="{{ __('time.staffing.type') }}">
                                    <option>{{ $row['stale_label'] }} — {{ __('time.staffing.stale') }}</option>
                                </select>
                            @else
                                <select class="wp-select wp-select--compact" wire:model="staffingRows.{{ $selectedLocation->id }}.{{ $index }}.type_id" aria-label="{{ __('time.staffing.type') }}">
                                    <option value="">{{ __('time.staffing.type_placeholder') }}</option>
                                    @foreach ($staffingTypeOptions as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <input class="wp-input wp-input--compact" type="number" min="1" max="999" wire:model="staffingRows.{{ $selectedLocation->id }}.{{ $index }}.min" aria-label="{{ __('time.staffing.count') }}">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="removeStaffingRow({{ $selectedLocation->id }}, {{ $index }})" aria-label="{{ __('time.staffing.remove') }}">✕</button>
                        </div>
                    @endforeach
                    <div>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="addStaffingRow({{ $selectedLocation->id }})">{{ __('time.staffing.add') }}</button>
                    </div>
                    <p class="wp-hint">{{ __('time.staffing.hint') }}</p>
                </div>
                @can('update', $selectedLocation)
                    <div class="wp-cluster wp-cluster--end">
                        <button type="button" class="btn btn--primary" wire:click="saveLocation({{ $selectedLocation->id }})">{{ __('time.staffing.save') }}</button>
                    </div>
                @endcan
            </div>
        @endif
    @endif
</div>
