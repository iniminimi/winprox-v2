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
                <div class="wp-cluster wp-cluster--end">
                    @if ($copyTargets->isNotEmpty())
                        <button type="button" class="btn btn--ghost" wire:click="openCopyModal">{{ __('time.staffing.copy_button') }}</button>
                    @endif
                    @can('update', $selectedLocation)
                        <button type="button" class="btn btn--primary" wire:click="saveLocation({{ $selectedLocation->id }})">{{ __('time.staffing.save') }}</button>
                    @endcan
                </div>
            </div>
        @endif
    @endif

    @if ($showCopyModal && $selectedLocation !== null)
        <x-wp-modal closeMethod="closeCopyModal" aria-labelledby="staffing-copy-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="staffing-copy-title" class="wp-section-title">{{ __('time.staffing.copy_title') }}</h2>
                    <x-wp-modal-close wire:click="closeCopyModal" />
                </div>
                <p class="wp-muted wp-text-sm">{{ __('time.staffing.copy_hint', ['from' => $selectedLocation->name]) }}</p>
                <div class="wp-filter-cell">
                    <label class="wp-filter-inline-label" for="staffing-copy-target">{{ __('time.staffing.copy_target_label') }}</label>
                    <select id="staffing-copy-target" class="wp-select" wire:model.live="copyTargetId">
                        <option value="">{{ __('time.staffing.copy_target_placeholder') }}</option>
                        @foreach ($copyTargets as $target)
                            <option value="{{ $target->id }}">{{ $target->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeCopyModal">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="copyToLocation" @disabled(! $copyTargetId)>{{ __('time.staffing.copy_confirm') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
