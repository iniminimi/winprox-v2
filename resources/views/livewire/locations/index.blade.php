<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('locations.title') }}</h1>
            <p class="wp-muted">{{ __('locations.subtitle') }}</p>
        </div>
        <button type="button" class="btn btn--primary" wire:click="openCreate">
            {{ __('locations.add') }}
        </button>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-filter-row">
            <input type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('locations.search_placeholder') }}" />
            <label class="wp-check">
                <input type="checkbox" wire:model.live="showInactive" />
                <span>{{ __('locations.show_inactive') }}</span>
            </label>
        </div>
        <p class="wp-muted">{{ __('locations.search_hint') }}</p>

        <div class="wp-list">
            @forelse ($locations as $location)
                <div class="wp-location-row" wire:key="loc-{{ $location->id }}">
                    <a href="{{ route('locations.show', $location) }}" class="wp-location-row-link">
                        <div class="wp-grow">
                            <div class="wp-cluster">
                                <span class="wp-section-title">{{ $location->name }}</span>
                                <span class="wp-pill wp-pill--closed">{{ __('locations.unit_count', ['count' => $location->units_count]) }}</span>
                                @if (! $location->is_active)
                                    <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                                @endif
                            </div>
                            @if ($location->formattedAddress())
                                <p class="wp-muted">{{ $location->formattedAddress() }}</p>
                            @endif
                        </div>
                    </a>
                    @if ($location->is_active)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivate({{ $location->id }})"
                                wire:confirm="{{ __('locations.confirm_deactivate') }}">
                            {{ __('locations.deactivate') }}
                        </button>
                    @endif
                </div>
            @empty
                <p class="wp-muted">{{ __('locations.empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($showModal)
        <div class="wp-modal">
            <form wire:submit="save" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingLocationId ? __('locations.edit_title') : __('locations.create_title') }}
                    </h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.fields.name') }}</span>
                    <input type="text" class="wp-input" wire:model="name" />
                    @error('name') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <div class="wp-form-grid-2">
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.fields.street') }}</span>
                        <input type="text" class="wp-input" wire:model="street" />
                        @error('street') <span class="wp-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.fields.house_number') }}</span>
                        <input type="text" class="wp-input" wire:model="house_number" />
                        @error('house_number') <span class="wp-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="wp-form-grid-2">
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.fields.postal_code') }}</span>
                        <input type="text" class="wp-input" wire:model="postal_code" />
                        @error('postal_code') <span class="wp-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.fields.city') }}</span>
                        <input type="text" class="wp-input" wire:model="city" />
                        @error('city') <span class="wp-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.fields.country_code') }}</span>
                    <input type="text" class="wp-input" wire:model="country_code" maxlength="2" />
                    @error('country_code') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.fields.notes') }}</span>
                    <textarea class="wp-input" rows="3" wire:model="notes"></textarea>
                    @error('notes') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('locations.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
