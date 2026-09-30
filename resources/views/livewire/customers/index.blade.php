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
            @if ($canImportCustomersCsv ?? false)
                @can('create', \App\Models\Customer::class)
                    <button type="button" class="btn btn--ghost" wire:click="openCustomersCsvImportModal">
                        {{ __('customers.customers_csv.button') }}
                    </button>
                @endcan
            @endif
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

    @if ($customersImportNotice)
        <div @class(['wp-flash', $customersImportNoticeType === 'error' ? 'wp-flash--danger' : 'wp-flash--success'])>
            {{ $customersImportNotice }}
        </div>
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
                @endphp
                <div class="wp-stack-tight {{ $isOpen ? 'wp-team-row--expanded' : '' }}" wire:key="customer-{{ $customer->id }}">
                    <div class="wp-data-row {{ $isOpen ? 'wp-team-header--expanded' : '' }}">
                        <div class="wp-data-row-main">
                            <button type="button"
                                    class="wp-team-row-toggle"
                                    wire:click="toggleExpanded({{ $customer->id }})"
                                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                    aria-controls="customer-panel-{{ $customer->id }}">
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron {{ $isOpen ? 'is-open' : '' }}" />
                                <span class="wp-data-row-title">{{ $customer->name }}</span>
                            </button>
                            <span class="wp-muted">
                                @if ($customer->contact_name){{ $customer->contact_name }} · @endif
                                @if ($customer->email){{ $customer->email }} · @endif
                                {{ trans_choice('customers.locations_count', $customerLocations->count(), ['count' => $customerLocations->count()]) }}
                            </span>
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
                        <div id="customer-panel-{{ $customer->id }}" class="wp-team-workers-panel" wire:key="customer-{{ $customer->id }}-locations">
                            <div class="wp-list">
                                @forelse ($customerLocations as $location)
                                    <div class="wp-data-row" wire:key="customer-location-{{ $location->id }}">
                                        <div class="wp-data-row-main">
                                            <span class="wp-data-row-title">{{ $location->name }}</span>
                                            @php($locationAddress = $location->formattedAddress())
                                            @if ($locationAddress !== '' || $location->contractual_relationship_reference)
                                                <p class="wp-issue-card-meta">
                                                    {{ $locationAddress }}
                                                    @if ($location->contractual_relationship_reference)
                                                        · {{ __('locations.fields.ddt') }}: {{ $location->contractual_relationship_reference }}
                                                    @endif
                                                </p>
                                            @endif
                                            @if (! $location->hasWorkVisitPin())
                                                <p class="wp-hint">{{ __('customers.location_no_pin') }}</p>
                                            @endif
                                        </div>
                                        <div class="wp-cluster wp-cluster--tight">
                                            @unless ($location->is_active)
                                                <span class="wp-pill wp-pill--closed">{{ __('customers.inactive') }}</span>
                                            @endunless
                                            @can('update', $location)
                                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openLocationEdit({{ $location->id }})">{{ __('common.button.edit') }}</button>
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
                        </div>
                    @endif
                </div>
            @empty
                <p class="wp-muted">{{ __('customers.empty') }}</p>
            @endforelse
        </div>
    </div>

    @include('livewire.customers.customer-import-history', ['batches' => $customerImportBatches])

    @if ($showCustomersCsvImportModal)
        <x-wp-modal closeMethod="closeCustomersCsvImportModal" aria-labelledby="customers-csv-title">
            <div class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="customers-csv-title" class="wp-section-title">{{ __('customers.customers_csv.title') }}</h2>
                    <x-wp-modal-close wire:click="closeCustomersCsvImportModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('customers.customers_csv.hint') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="customers-csv-file">{{ __('customers.import_file_label') }}</label>
                        <div class="wp-cluster">
                            <input type="file" id="customers-csv-file" class="wp-input wp-grow" wire:model="customersCsvImportFile" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadCustomersSampleCsv">
                                {{ __('customers.import_sample.download_sample_csv') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadCustomersSampleXlsx">
                                {{ __('customers.import_sample.download_sample_xlsx') }}
                            </button>
                        </div>
                        @error('customersCsvImportFile') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($customersCsvImportErrors !== [])
                        <div class="wp-flash wp-flash--danger">
                            <ul class="wp-form-error-list">
                                @foreach ($customersCsvImportErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeCustomersCsvImportModal">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="importCustomersCsv" wire:loading.attr="disabled" wire:target="importCustomersCsv,customersCsvImportFile" :disabled="$customersCsvImportFile === null">
                        <x-wp-spinner wire:loading wire:target="importCustomersCsv,customersCsvImportFile" class="wp-mr-2" />
                        <span wire:loading.remove wire:target="importCustomersCsv,customersCsvImportFile">{{ __('customers.import_submit') }}</span>
                        <span wire:loading wire:target="importCustomersCsv,customersCsvImportFile">{{ __('customers.import_submit_loading') }}</span>
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif

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
                    <h2 id="customer-location-modal-title" class="wp-h2">
                        {{ $editingLocationId ? __('customers.location_edit_title') : __('customers.location_create_title') }}
                    </h2>
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
                        <x-wp-tooltip :text="__('customers.location_form.address_paste_hint')" wrap>
                            <label class="wp-label" for="locationFormStreet">{{ __('customers.location_form.street') }}</label>
                        </x-wp-tooltip>
                        <input type="text" id="locationFormStreet" class="wp-input" wire:model="locationFormStreet" autocomplete="off" @paste="$wire.applyLocationAddressPaste($event.clipboardData.getData('text'))">
                        @error('locationFormStreet') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('street') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormHouseNumber">{{ __('customers.location_form.house_number') }}</label>
                        <input type="text" id="locationFormHouseNumber" class="wp-input" wire:model="locationFormHouseNumber" autocomplete="off" @paste="$wire.applyLocationAddressPaste($event.clipboardData.getData('text'))">
                        @error('house_number') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-form-grid-2">
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormPostalCode">{{ __('customers.location_form.postal_code') }}</label>
                        <input type="text" id="locationFormPostalCode" class="wp-input" wire:model="locationFormPostalCode" autocomplete="off" @paste="$wire.applyLocationAddressPaste($event.clipboardData.getData('text'))">
                        @error('postal_code') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="locationFormCity">{{ __('customers.location_form.city') }}</label>
                        <input type="text" id="locationFormCity" class="wp-input" wire:model="locationFormCity" autocomplete="off" @paste="$wire.applyLocationAddressPaste($event.clipboardData.getData('text'))">
                        @error('city') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-field">
                    <label class="wp-label" for="locationFormCountryCode">{{ __('locations.fields.country_code') }}</label>
                    <input type="text" id="locationFormCountryCode" class="wp-input" wire:model="locationFormCountryCode" maxlength="2" autocomplete="off">
                    @error('country_code') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                @if ($locationDdtVisible)
                    <div class="wp-field">
                        <x-wp-tooltip :text="__('locations.fields.ddt_tooltip')" wrap>
                            <label class="wp-label" for="locationFormDdt">{{ __('locations.fields.ddt') }}</label>
                        </x-wp-tooltip>
                        <input type="text" id="locationFormDdt" class="wp-input" wire:model="locationFormDdt" maxlength="13" autocomplete="off">
                        <p class="wp-hint">{{ __('locations.fields.ddt_hint') }}</p>
                        @error('contractual_relationship_reference') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                @include('partials.wp-gps-coords-fields', [
                    'latProperty' => 'locationFormLatitude',
                    'lngProperty' => 'locationFormLongitude',
                    'applyMethod' => 'applyLocationGpsPair',
                    'searchProperties' => ['locationFormStreet', 'locationFormHouseNumber', 'locationFormPostalCode', 'locationFormCity'],
                    'hintKey' => 'customers.location_form.pin_hint',
                    'latError' => 'latitude',
                    'lngError' => 'longitude',
                ])

                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeLocationModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
