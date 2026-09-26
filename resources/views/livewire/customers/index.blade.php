<div class="wp-stack" data-manual-capture="customers-list">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="team"
                :title="__('customers.title')"
                :subtitle="__('customers.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            @can('create', \App\Models\Customer::class)
                <button type="button" class="btn btn--primary" wire:click="openCreate">
                    {{ __('customers.add') }}
                </button>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="wp-flash wp-flash--danger">{{ session('error') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-filter-row">
            <input type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('customers.search_placeholder') }}" />
            <label class="wp-check">
                <input type="checkbox" wire:model.live="showInactive" />
                <span>{{ __('customers.show_inactive') }}</span>
            </label>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($customers as $customer)
                @php
                    $isOpen = (bool) ($expandedCustomerIds[$customer->id] ?? false);
                    $customerLocations = $customer->locations;
                    $activeLocationCount = $customerLocations->where('is_active', true)->count();
                @endphp
                <div class="wp-data-row" wire:key="customer-{{ $customer->id }}">
                    <div class="wp-data-row-main">
                        <button type="button" class="wp-issue-row-link wp-stack-tight" wire:click="toggleExpanded({{ $customer->id }})">
                            <p class="wp-issue-card-title">{{ $customer->name }}</p>
                            <p class="wp-issue-card-meta">
                                @if ($customer->contact_name){{ $customer->contact_name }} · @endif
                                @if ($customer->email){{ $customer->email }} · @endif
                                {{ __('customers.locations_count', ['count' => $customerLocations->count()]) }}
                            </p>
                        </button>
                    </div>
                    <div class="wp-cluster wp-cluster--tight">
                        @unless ($customer->is_active)
                            <span class="wp-pill wp-pill--closed">{{ __('customers.inactive') }}</span>
                        @endunless
                        @can('update', $customer)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openLocationCreate({{ $customer->id }})">
                                {{ __('customers.add_location') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $customer->id }})">{{ __('common.button.edit') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleCustomerActive({{ $customer->id }})">
                                {{ $customer->is_active ? __('locations.deactivate') : __('locations.activate') }}
                            </button>
                        @endcan
                    </div>
                </div>

                @if ($isOpen)
                    <div class="wp-card wp-card-pad wp-stack-tight" wire:key="customer-{{ $customer->id }}-locations">
                        @forelse ($customerLocations as $location)
                            <div class="wp-data-row" wire:key="customer-location-{{ $location->id }}">
                                <div class="wp-data-row-main">
                                    <span class="wp-data-row-title">{{ $location->name }}</span>
                                    <p class="wp-issue-card-meta">{{ $location->formattedAddress() }}</p>
                                    @if (! $location->hasWorkVisitPin())
                                        <p class="wp-hint">{{ __('customers.location_no_pin') }}</p>
                                    @endif
                                </div>
                                <div class="wp-cluster wp-cluster--tight">
                                    @unless ($location->is_active)
                                        <span class="wp-pill wp-pill--closed">{{ __('customers.inactive') }}</span>
                                    @endunless
                                    @can('update', $location)
                                        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleLocationActive({{ $location->id }})">
                                            {{ $location->is_active ? __('locations.deactivate') : __('locations.activate') }}
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="wp-muted">{{ __('customers.no_locations') }}</p>
                        @endforelse
                    </div>
                @endif
            @empty
                <p class="wp-muted">{{ __('customers.empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal" aria-labelledby="customer-modal-title">
            <form wire:submit="saveCustomer" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="customer-modal-title" class="wp-h2">
                        {{ $editingCustomerId ? __('customers.edit_title') : __('customers.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="customerFormName">{{ __('customers.form.name') }}</label>
                    <input type="text" id="customerFormName" class="wp-input" wire:model.live.debounce.300ms="customerFormName" autocomplete="off">
                    @if ($customerNameMatches !== [] && $editingCustomerId === null)
                        <p class="wp-hint">
                            {{ __('customers.form.name_matches') }}
                            {{ implode(', ', array_column($customerNameMatches, 'name')) }}
                        </p>
                    @endif
                    @error('customerFormName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="customerFormContactName">{{ __('customers.form.contact_name') }}</label>
                    <input type="text" id="customerFormContactName" class="wp-input" wire:model="customerFormContactName" autocomplete="off">
                    @error('customerFormContactName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="customerFormEmail">{{ __('customers.form.email') }}</label>
                    <input type="email" id="customerFormEmail" class="wp-input" wire:model="customerFormEmail" autocomplete="off">
                    @error('customerFormEmail') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="customerFormPhone">{{ __('customers.form.phone') }}</label>
                    <input type="text" id="customerFormPhone" class="wp-input" wire:model="customerFormPhone" autocomplete="off">
                    @error('customerFormPhone') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showLocationModal)
        <x-wp-modal closeMethod="closeLocationModal" aria-labelledby="customer-location-modal-title">
            <form wire:submit="saveLocation" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="customer-location-modal-title" class="wp-h2">{{ __('customers.location_create_title') }}</h2>
                    <x-wp-modal-close wire:click="closeLocationModal" />
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="locationFormName">{{ __('customers.location_form.name') }}</label>
                    <input type="text" id="locationFormName" class="wp-input" wire:model="locationFormName" autocomplete="off">
                    <p class="wp-hint">{{ __('customers.location_form.name_hint') }}</p>
                    @error('locationFormName') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-form-grid-2">
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormStreet">{{ __('customers.location_form.street') }}</label>
                        <input type="text" id="locationFormStreet" class="wp-input" wire:model="locationFormStreet" autocomplete="off">
                        @error('locationFormStreet') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('street') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormHouseNumber">{{ __('customers.location_form.house_number') }}</label>
                        <input type="text" id="locationFormHouseNumber" class="wp-input" wire:model="locationFormHouseNumber" autocomplete="off">
                        @error('house_number') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-form-grid-2">
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormPostalCode">{{ __('customers.location_form.postal_code') }}</label>
                        <input type="text" id="locationFormPostalCode" class="wp-input" wire:model="locationFormPostalCode" autocomplete="off">
                        @error('postal_code') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormCity">{{ __('customers.location_form.city') }}</label>
                        <input type="text" id="locationFormCity" class="wp-input" wire:model="locationFormCity" autocomplete="off">
                        @error('city') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($checkmateMode || ($tenant?->presenceComplianceEnabled() ?? false))
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormDdt">{{ __('locations.form.ddt') }}</label>
                        <input type="text" id="locationFormDdt" class="wp-input" wire:model="locationFormDdt" autocomplete="off" maxlength="13">
                        <p class="wp-hint">{{ __('locations.form.ddt_hint') }}</p>
                        @error('contractual_relationship_reference') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="wp-form-grid-2">
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormLatitude">{{ __('customers.location_form.latitude') }}</label>
                        <input type="text" id="locationFormLatitude" class="wp-input" wire:model="locationFormLatitude" autocomplete="off" inputmode="decimal">
                        @error('latitude') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormLongitude">{{ __('customers.location_form.longitude') }}</label>
                        <input type="text" id="locationFormLongitude" class="wp-input" wire:model="locationFormLongitude" autocomplete="off" inputmode="decimal">
                        @error('longitude') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="wp-hint">{{ __('customers.location_form.pin_hint') }}</p>

                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeLocationModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
