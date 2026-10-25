@if (! ($checkmateMode ?? false) && (($minStaffingTypeOptions ?? []) !== [] || $locationFormMinStaffing !== []))
    <div class="wp-field" x-data="{ open: false }">
        <span class="wp-label">{{ __('locations.min_staffing.label') }}</span>

        <div class="wp-field-panel" :class="{ 'is-open': open }">
            <button
                type="button"
                class="wp-field-panel__trigger"
                @click="open = !open"
                :aria-expanded="open"
            >
                <span>{{ __('locations.min_staffing.open') }}</span>
                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
            </button>

            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                <p class="wp-hint">{{ __('locations.min_staffing.hint') }}</p>

                @foreach ($locationFormMinStaffing as $minStaffingIndex => $minStaffingRow)
                    <div class="wp-cluster">
                        @if ($minStaffingRow['stale_label'] !== null)
                            <select class="wp-select wp-select--compact" disabled aria-label="{{ __('locations.min_staffing.type') }}">
                                <option>{{ $minStaffingRow['stale_label'] }} — {{ __('locations.min_staffing.stale') }}</option>
                            </select>
                        @else
                            <select
                                class="wp-select wp-select--compact"
                                wire:model="locationFormMinStaffing.{{ $minStaffingIndex }}.type_id"
                                aria-label="{{ __('locations.min_staffing.type') }}"
                            >
                                <option value="">{{ __('locations.min_staffing.type_placeholder') }}</option>
                                @foreach (($minStaffingTypeOptions ?? []) as $minStaffingType)
                                    <option value="{{ $minStaffingType['id'] }}">{{ $minStaffingType['label'] }}</option>
                                @endforeach
                            </select>
                        @endif
                        <input
                            type="number"
                            class="wp-input wp-input--compact"
                            min="1"
                            max="99"
                            wire:model="locationFormMinStaffing.{{ $minStaffingIndex }}.min"
                            aria-label="{{ __('locations.min_staffing.count') }}"
                        />
                        <button
                            type="button"
                            class="btn btn--ghost btn--sm"
                            wire:click="removeMinStaffingRow({{ $minStaffingIndex }})"
                            aria-label="{{ __('locations.min_staffing.remove') }}"
                        >
                            <x-wp-icon name="x-mark" />
                        </button>
                    </div>
                @endforeach

                <div class="wp-row">
                    <button
                        type="button"
                        class="btn btn--ghost btn--sm"
                        wire:click="addMinStaffingRow"
                    >
                        <span>{{ __('locations.min_staffing.add') }}</span>
                    </button>
                </div>

                @error('locationFormMinStaffing') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
@endif
