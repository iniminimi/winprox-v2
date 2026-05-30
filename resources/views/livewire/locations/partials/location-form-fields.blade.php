<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.name') }}</span>
    <input type="text" class="wp-input" wire:model="name" />
    @error('name') <span class="wp-error">{{ $message }}</span> @enderror
</label>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.street') }}</span>
        <input type="text" class="wp-input" wire:model="street" />
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.house_number') }}</span>
        <input type="text" class="wp-input" wire:model="house_number" />
    </label>
</div>

<div class="wp-form-grid-2">
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.postal_code') }}</span>
        <input type="text" class="wp-input" wire:model="postal_code" />
    </label>
    <label class="wp-field">
        <span class="wp-label">{{ __('locations.fields.city') }}</span>
        <input type="text" class="wp-input" wire:model="city" />
    </label>
</div>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.country_code') }}</span>
    <input type="text" class="wp-input" wire:model="country_code" maxlength="2" />
</label>

<label class="wp-field">
    <span class="wp-label">{{ __('locations.fields.notes') }}</span>
    <textarea class="wp-input" rows="3" wire:model="notes"></textarea>
</label>
