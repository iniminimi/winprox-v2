<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="locations"
                :title="__('locations.title')"
                help-page="locations.list"
                :subtitle="__('locations.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            <button type="button" class="btn btn--ghost btn--sm" wire:click="openImportModal">
                {{ __('locations.import') }}
            </button>
            <button type="button" class="btn btn--primary @if($locations->isEmpty()) wp-badge-critical @endif" wire:click="openCreate">
                {{ __('locations.add') }}
            </button>
        </div>
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

        <div class="wp-list wp-list--entity-rows">
            @forelse ($locations as $location)
                @php
                    $addressLine = $location->formattedAddress()
                        ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
                        : '';
                @endphp
                <div class="wp-issue-row" wire:key="loc-{{ $location->id }}-{{ $location->is_active }}">
                    <a href="{{ route('locations.show', $location) }}" class="wp-issue-row-link wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $location->name }}</p>
                        @if ($addressLine !== '')
                            <p class="wp-issue-card-meta">{{ $addressLine }}</p>
                        @endif
                    </a>
                    <div class="wp-issue-row-meta">
                        <span class="wp-pill wp-pill--closed">{{ __('locations.unit_count', ['count' => $location->units_count]) }}</span>
                        @if (! $location->is_active)
                            <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                        @endif
                    </div>
                    @if ($location->is_active)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivate({{ $location->id }})"
                                wire:confirm="{{ __('locations.confirm_deactivate') }}">
                            {{ __('locations.deactivate') }}
                        </button>
                    @else
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="activate({{ $location->id }})">
                            {{ __('locations.activate') }}
                        </button>
                    @endif
                </div>
            @empty
                @if ($hasInactiveLocations && !$showInactive)
                    <p class="wp-muted">{{ __('locations.empty_inactive') }}</p>
                @else
                    <p class="wp-muted">{{ __('locations.empty') }}</p>
                @endif
            @endforelse
        </div>
    </div>

    <livewire:locations.import-history />

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal" aria-labelledby="location-modal-title">
            <form wire:submit="save" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="location-modal-title" class="wp-section-title">
                        {{ $editingLocationId ? __('locations.edit_title') : __('locations.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    @include('livewire.locations.partials.location-form-fields')
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('locations.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showImportModal)
        <x-wp-modal closeMethod="closeImportModal" aria-labelledby="import-modal-title">
            <div class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="import-modal-title" class="wp-section-title">{{ __('locations.import_title') }}</h2>
                    <x-wp-modal-close wire:click="closeImportModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('locations.import_hint') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="import-file">{{ __('locations.import_file_label') }}</label>
                        <div class="wp-cluster">
                            <input type="file" id="import-file" class="wp-input wp-grow" wire:model="importFile" accept=".csv" />
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadSampleCsv">
                                {{ __('locations.import.download_sample_csv') }}
                            </button>
                        </div>
                        @error('importFile') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($importErrors)
                        <div class="wp-flash wp-flash--danger">
                            <ul class="wp-form-error-list">
                                @foreach ($importErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeImportModal">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="importUnits" wire:loading.attr="disabled" :disabled="$importFile === null">
                        <x-wp-spinner wire:loading class="wp-mr-2" />
                        <span wire:loading.remove>{{ __('locations.import_submit') }}</span>
                        <span wire:loading>{{ __('locations.import_submit_loading') }}</span>
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
