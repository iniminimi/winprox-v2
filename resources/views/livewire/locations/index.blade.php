<div class="wp-stack" data-manual-capture="locations-list">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @else
    @php
        $showLocationsOnboarding = ! $hasAnyLocation;
        $pulseCategoriesCard = $showLocationsOnboarding && $categories->isEmpty() && ! $showCategoriesSection;
        $pulseAddLocationButton = $showLocationsOnboarding && $categories->isNotEmpty();
    @endphp
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
            <button type="button" @class(['btn', 'btn--primary', 'wp-btn--prio-pulse' => $pulseAddLocationButton]) wire:click="openCreate">
                {{ __('locations.add') }}
            </button>
        </div>
    </div>

    @if ($unitsImportNotice)
        <div @class([
            'wp-flash',
            'wp-flash--success' => $unitsImportNoticeType !== 'error',
            'wp-flash--danger' => $unitsImportNoticeType === 'error',
        ])>{{ $unitsImportNotice }}</div>
    @elseif (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    <x-wp-disclosure-card
        :title="__('locations.categories.title')"
        :subtitle="__('locations.categories.click_to_manage')"
        :count="$categories->count()"
        entangle="showCategoriesSection"
        @class(['wp-card--prio-pulse' => $pulseCategoriesCard])
    >
        <x-slot:toolbar>
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCategoriesModal">
                {{ __('locations.categories.add') }}
            </button>
        </x-slot:toolbar>

        @if ($categories->isNotEmpty())
            <div class="wp-list wp-list--entity-rows">
                @foreach ($categories as $category)
                    <div class="wp-issue-row" wire:key="category-{{ $category->id }}">
                        <div class="wp-grow wp-stack-tight">
                            <p class="wp-issue-card-title">{{ $category->name }}</p>
                        </div>
                        <div class="wp-cluster">
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditCategory({{ $category->id }})">{{ __('common.button.edit') }}</button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="{{ __('locations.categories.confirm_delete') }}">{{ __('common.button.delete') }}</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="wp-muted">{{ __('locations.categories.empty') }}</p>
        @endif
    </x-wp-disclosure-card>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-grow">
            <h2 class="wp-section-title">{{ __('locations.title') }}</h2>
            <p class="wp-muted">{{ __('locations.search_hint') }}</p>
        </div>
        <div class="wp-filter-row">
            <input type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('locations.search_placeholder') }}" />
            <label class="wp-check">
                <input type="checkbox" wire:model.live="showInactive" />
                <span>{{ __('locations.show_inactive') }}</span>
            </label>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($locations as $location)
                @php
                    $addressLine = $location->formattedAddress()
                        ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
                        : '';
                @endphp
                <div class="wp-issue-row" wire:key="loc-{{ $location->id }}-{{ $location->is_active }}">
                    <a href="{{ route('locations.show', $location) }}" class="wp-issue-row-link wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $location->localizedName() }}</p>
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
                @if ($showLocationsOnboarding)
                    <div class="wp-card wp-card-pad wp-onboarding-card">
                        <div class="wp-stack">
                            <p class="wp-text-body"><strong>{{ $categories->isEmpty() ? __('locations.onboarding.title_categories') : __('locations.onboarding.title_locations') }}</strong></p>
                        </div>
                    </div>
                @else
                    <p class="wp-muted">{{ ($hasInactiveLocations && ! $showInactive) ? __('locations.empty_inactive') : __('locations.empty') }}</p>
                @endif
            @endforelse
        </div>
    </div>

    @include('livewire.locations.import-history', ['batches' => $unitImportBatches])

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

    @if ($showCategoriesModal)
        <x-wp-modal closeMethod="closeCategoriesModal">
            <form wire:submit="saveCategory" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ $editingCategoryId !== null ? __('locations.categories.edit_title') : __('locations.categories.add_title') }}</h2>
                    <x-wp-modal-close wire:click="closeCategoriesModal" />
                </div>

                <div class="wp-field">
                    <label class="wp-label">{{ __('locations.categories.fields.name') }}</label>
                    <input type="text" class="wp-input" wire:model="categoryName" />
                    @error('categoryName') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <label class="wp-check">
                        <input type="checkbox" wire:model="categoryAllowGpsLocation" />
                        <span>{{ __('locations.categories.fields.allow_gps_location') }}</span>
                    </label>
                    <p class="wp-hint">{{ __('locations.categories.allow_gps_location_hint') }}</p>
                    @error('categoryAllowGpsLocation') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <h3 class="wp-label">{{ __('locations.categories.fields.teams') }}</h3>
                    <p class="wp-hint">{{ __('locations.categories.teams_subtitle') }}</p>
                </div>

                @if ($teams->isNotEmpty())
                    <div class="wp-grid wp-grid--2">
                        @foreach ($teams as $team)
                            @php
                                $isTeamSelected = in_array($team->id, $selectedCategoryTeamIds, true);
                            @endphp
                            <label class="wp-check">
                                <input type="checkbox"
                                       wire:model.live="selectedCategoryTeamIds"
                                       value="{{ $team->id }}"
                                       @if ($isTeamSelected) checked @endif>
                                <span>{{ $team->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="wp-muted">{{ __('locations.categories.teams_empty') }}</p>
                @endif
                @error('selectedCategoryTeamIds') <p class="wp-error">{{ $message }}</p> @enderror

                <div class="wp-cluster wp-cluster--tight">
                    @if ($editingCategoryId !== null)
                        <button type="button" class="btn btn--ghost" wire:click="cancelEditCategory">{{ __('common.button.cancel') }}</button>
                    @endif
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="saveCategory">
                        <span wire:loading wire:target="saveCategory" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ $editingCategoryId !== null ? __('common.button.save') : __('locations.categories.add') }}</span>
                    </button>
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
                                {{ __('locations.import_sample.download_sample_csv') }}
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
                    <button type="button" class="btn btn--primary" wire:click="importUnits" wire:loading.attr="disabled" wire:target="importUnits,importFile" :disabled="$importFile === null">
                        <x-wp-spinner wire:loading wire:target="importUnits,importFile" class="wp-mr-2" />
                        <span wire:loading.remove wire:target="importUnits,importFile">{{ __('locations.import_submit') }}</span>
                        <span wire:loading wire:target="importUnits,importFile">{{ __('locations.import_submit_loading') }}</span>
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
    @endif
</div>
