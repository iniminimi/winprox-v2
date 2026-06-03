<label class="wp-field">
    <span class="wp-label">
        {{ __('locations.fields.name') }}
        <span class="wp-label-hint">({{ __('locations.fields.name_optional') }})</span>
    </span>
    <input type="text" class="wp-input" wire:model="locationFormName" placeholder="{{ __('locations.fields.name_placeholder') }}" />
    @error('name') <span class="wp-error">{{ $message }}</span> @enderror
</label>
<p class="wp-hint">{{ __('locations.form.identity_hint') }}</p>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.street') }}</span>
        <input type="text" class="wp-input" wire:model="locationFormStreet" />
        @error('street') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.house_number') }}</span>
        <input type="text" class="wp-input" wire:model="locationFormHouseNumber" />
        @error('house_number') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
</div>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.postal_code') }}</span>
        <input type="text" class="wp-input" wire:model="locationFormPostalCode" />
        @error('postal_code') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.city') }}</span>
        <input type="text" class="wp-input" wire:model="locationFormCity" />
        @error('city') <span class="wp-error">{{ $message }}</span> @enderror
    </label>
</div>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.country_code') }}</span>
    <input type="text" class="wp-input" wire:model="locationFormCountryCode" maxlength="2" />
    @error('country_code') <span class="wp-error">{{ $message }}</span> @enderror
</label>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.notes') }}</span>
    <textarea class="wp-input" rows="3" wire:model="locationFormNotes"></textarea>
    @error('notes') <span class="wp-error">{{ $message }}</span> @enderror
</label>
