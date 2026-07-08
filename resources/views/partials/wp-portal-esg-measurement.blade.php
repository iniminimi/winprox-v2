@php($indicator = $task->issue?->esgIndicator)
@if ($indicator)
    <div class="wp-field">
        <label class="wp-label" for="esg-value-{{ $task->id }}">
            {{ __('esg.portal.measurement_label', ['name' => $indicator->name]) }}
        </label>

        @if ($indicator->type === \App\Enums\EsgIndicatorType::Numeric)
            <div class="wp-cluster wp-cluster--tight">
                <input id="esg-value-{{ $task->id }}"
                       type="number"
                       step="any"
                       inputmode="decimal"
                       class="wp-input"
                       wire:model="completingEsgValueNumeric"
                       placeholder="{{ __('esg.portal.numeric_placeholder') }}">
                @if (filled($indicator->unit_of_measure))
                    <span class="wp-muted wp-text-sm">{{ $indicator->unit_of_measure }}</span>
                @endif
            </div>
            @error('completingEsgValueNumeric') <p class="wp-error">{{ $message }}</p> @enderror
        @elseif ($indicator->type === \App\Enums\EsgIndicatorType::Boolean)
            <select id="esg-value-{{ $task->id }}" class="wp-select" wire:model="completingEsgValueBoolean">
                <option value="">{{ __('esg.portal.boolean_choose') }}</option>
                <option value="1">{{ __('esg.portal.boolean_yes') }}</option>
                <option value="0">{{ __('esg.portal.boolean_no') }}</option>
            </select>
            @error('completingEsgValueBoolean') <p class="wp-error">{{ $message }}</p> @enderror
        @elseif ($indicator->type === \App\Enums\EsgIndicatorType::String)
            <input id="esg-value-{{ $task->id }}"
                   type="text"
                   class="wp-input"
                   wire:model="completingEsgValueString"
                   placeholder="{{ __('esg.portal.string_placeholder') }}">
            @error('completingEsgValueString') <p class="wp-error">{{ $message }}</p> @enderror
        @elseif ($indicator->type === \App\Enums\EsgIndicatorType::Json)
            <textarea id="esg-value-{{ $task->id }}"
                      class="wp-textarea"
                      rows="3"
                      wire:model="completingEsgValueJson"
                      placeholder="{{ __('esg.portal.json_placeholder') }}"></textarea>
            @error('completingEsgValueJson') <p class="wp-error">{{ $message }}</p> @enderror
        @endif
    </div>
@endif
