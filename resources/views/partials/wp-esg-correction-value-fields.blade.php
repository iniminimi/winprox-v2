@props(['indicator'])

@if ($indicator->type === \App\Enums\EsgIndicatorType::Numeric)
    <label class="wp-label" for="esg-correction-numeric">{{ __('esg.measurements.correction_fields.value') }}</label>
    <div class="wp-cluster wp-cluster--tight">
        <input id="esg-correction-numeric"
               type="number"
               step="any"
               class="wp-input"
               wire:model="correctionValueNumeric">
        @if (filled($indicator->unit_of_measure))
            <span class="wp-muted wp-text-sm">{{ $indicator->unit_of_measure }}</span>
        @endif
    </div>
    @error('correctionValueNumeric') <p class="wp-form-error">{{ $message }}</p> @enderror
@elseif ($indicator->type === \App\Enums\EsgIndicatorType::Boolean)
    <label class="wp-label" for="esg-correction-boolean">{{ __('esg.measurements.correction_fields.value') }}</label>
    <select id="esg-correction-boolean" class="wp-input" wire:model="correctionValueBoolean">
        <option value="">{{ __('esg.portal.boolean_choose') }}</option>
        <option value="1">{{ __('esg.portal.boolean_yes') }}</option>
        <option value="0">{{ __('esg.portal.boolean_no') }}</option>
    </select>
    @error('correctionValueBoolean') <p class="wp-form-error">{{ $message }}</p> @enderror
@elseif ($indicator->type === \App\Enums\EsgIndicatorType::Choice)
    <label class="wp-label" for="esg-correction-choice">{{ __('esg.measurements.correction_fields.value') }}</label>
    <select id="esg-correction-choice" class="wp-input" wire:model="correctionValueString">
        <option value="">{{ __('esg.portal.choice_choose') }}</option>
        @foreach ($indicator->normalizedChoiceOptions() as $option)
            <option value="{{ $option }}">{{ $indicator->localizedChoiceOptionLabel($option) }}</option>
        @endforeach
    </select>
    @error('correctionValueString') <p class="wp-form-error">{{ $message }}</p> @enderror
@elseif ($indicator->type === \App\Enums\EsgIndicatorType::MultiChoice)
    <p class="wp-label">{{ __('esg.measurements.correction_fields.value') }}</p>
    <div class="wp-stack-tight">
        @foreach ($indicator->normalizedChoiceOptions() as $option)
            <label class="wp-cluster wp-cluster--tight" wire:key="esg-correction-multi-{{ $option }}">
                <input type="checkbox"
                       value="{{ $option }}"
                       wire:model="correctionValueMultiChoice">
                <span>{{ $indicator->localizedChoiceOptionLabel($option) }}</span>
            </label>
        @endforeach
    </div>
    @error('correctionValueMultiChoice') <p class="wp-form-error">{{ $message }}</p> @enderror
@elseif ($indicator->type === \App\Enums\EsgIndicatorType::String)
    <label class="wp-label" for="esg-correction-string">{{ __('esg.measurements.correction_fields.value') }}</label>
    <input id="esg-correction-string" type="text" class="wp-input" wire:model="correctionValueString">
    @error('correctionValueString') <p class="wp-form-error">{{ $message }}</p> @enderror
@elseif ($indicator->type === \App\Enums\EsgIndicatorType::Json)
    <label class="wp-label" for="esg-correction-json">{{ __('esg.measurements.correction_fields.value') }}</label>
    <textarea id="esg-correction-json" class="wp-textarea" rows="3" wire:model="correctionValueJson"></textarea>
    @error('correctionValueJson') <p class="wp-form-error">{{ $message }}</p> @enderror
@endif

<label class="wp-label" for="esg-correction-recorded-at">{{ __('esg.measurements.correction_fields.recorded_at') }}</label>
<input id="esg-correction-recorded-at" type="datetime-local" class="wp-input" wire:model="correctionRecordedAt">
@error('correctionRecordedAt') <p class="wp-form-error">{{ $message }}</p> @enderror
