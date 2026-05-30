@props(['formKey' => null])

@php
    $wireField = static function (string $field) use ($formKey): string {
        if ($formKey === null) {
            return $field;
        }

        return match ($field) {
            'name' => $formKey.'Name',
            'street' => $formKey.'Street',
            'house_number' => $formKey.'HouseNumber',
            'postal_code' => $formKey.'PostalCode',
            'city' => $formKey.'City',
            'country_code' => $formKey.'CountryCode',
            'notes' => $formKey.'Notes',
            default => $field,
        };
    };
@endphp

<label class="wp-field">
    <span class="wp-label">
        {{ __('locations.fields.name') }}
        <span class="wp-label-hint">({{ __('locations.fields.name_optional') }})</span>
    </span>
    <input type="text" class="wp-input" wire:model="{{ $wireField('name') }}" placeholder="{{ __('locations.fields.name_placeholder') }}" />
    @error('name') <span class="wp-error">{{ $message }}</span> @enderror
</label>
<p class="wp-hint">{{ __('locations.form.identity_hint') }}</p>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.street') }}</span>
        <input type="text" class="wp-input" wire:model="{{ $wireField('street') }}" />
        @error('street') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.house_number') }}</span>
        <input type="text" class="wp-input" wire:model="{{ $wireField('house_number') }}" />
        @error('house_number') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.postal_code') }}</span>
        <input type="text" class="wp-input" wire:model="{{ $wireField('postal_code') }}" />
        @error('postal_code') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.city') }}</span>
        <input type="text" class="wp-input" wire:model="{{ $wireField('city') }}" />
        @error('city') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
</div>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.country_code') }}</span>
    <input type="text" class="wp-input" wire:model="{{ $wireField('country_code') }}" maxlength="2" />
    @error('country_code') <span class="wp-error">{{ $message }}</span> @enderror
</label>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.notes') }}</span>
    <textarea class="wp-input" rows="3" wire:model="{{ $wireField('notes') }}"></textarea>
    @error('notes') <span class="wp-error">{{ $message }}</span> @enderror
</label>
