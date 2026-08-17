<div class="wp-stack" data-manual-capture="{{ $this->isCategoriesSection() ? 'locations-categories' : 'locations-list' }}">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @else
    @php
        $isCategories = $this->isCategoriesSection();
        $showLocationsOnboarding = ! $isCategories && ! $hasAnyLocation;
        $pulseCategoriesAdd = $isCategories && $categories->isEmpty();
        $pulseAddLocationButton = $showLocationsOnboarding && $categories->isNotEmpty();
    @endphp
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="locations"
                :title="$isCategories ? __('locations.categories.title') : __('locations.title')"
                :help-page="$isCategories ? 'locations.categories' : 'locations.list'"
                :subtitle="$isCategories ? __('locations.categories.subtitle') : __('locations.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            @if ($isCategories)
                <button type="button" @class(['btn', 'btn--primary', 'wp-btn--prio-pulse' => $pulseCategoriesAdd]) wire:click="openCategoriesModal">
                    {{ __('locations.categories.add') }}
                </button>
            @else
                <button type="button" @class(['btn', 'btn--primary', 'wp-btn--prio-pulse' => $pulseAddLocationButton]) wire:click="openCreate">
                    {{ __('locations.add') }}
                </button>
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif

    @if ($isCategories)
        @if ($categories->isNotEmpty())
            <div class="wp-card wp-card-pad wp-stack">
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($categories as $category)
                        <div class="wp-issue-row" wire:key="category-{{ $category->id }}">
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ $category->localizedName() }}</p>
                            </div>
                            <div class="wp-cluster">
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditCategory({{ $category->id }})">{{ __('common.button.edit') }}</button>
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteCategory({{ $category->id }})"
                                        wire:confirm="{{ __('locations.categories.confirm_delete') }}">{{ __('common.button.delete') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="wp-card wp-card-pad wp-onboarding-card">
                <div class="wp-stack">
                    <p class="wp-text-body"><strong>{{ __('locations.onboarding.title_categories') }}</strong></p>
                    <p class="wp-muted">{{ __('locations.onboarding.text_categories') }}</p>
                </div>
            </div>
        @endif
    @else
        @if ($categories->isEmpty())
            <div class="wp-card wp-card-pad wp-onboarding-card">
                <div class="wp-stack">
                    <p class="wp-text-body"><strong>{{ __('locations.onboarding.title_categories') }}</strong></p>
                    <a href="{{ route('locations.index', ['section' => 'categories']) }}" class="btn btn--primary btn--sm wp-badge-critical">
                        {{ __('locations.onboarding.go_to_categories') }}
                    </a>
                </div>
            </div>
        @endif

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
                    @if ($showLocationsOnboarding && $categories->isNotEmpty())
                        <div class="wp-card wp-card-pad wp-onboarding-card">
                            <div class="wp-stack">
                                <p class="wp-text-body"><strong>{{ __('locations.onboarding.title_locations') }}</strong></p>
                            </div>
                        </div>
                    @elseif (! $showLocationsOnboarding)
                        <p class="wp-muted">{{ ($hasInactiveLocations && ! $showInactive) ? __('locations.empty_inactive') : __('locations.empty') }}</p>
                    @endif
                @endforelse
            </div>
        </div>
    @endif

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

                    @if ($editingLocationId && $editingLocation?->is_active)
                        <div class="wp-field" x-data="{ open: false }">
                            <span class="wp-label">{{ __('locations.translation_edit.label') }}</span>

                            <div class="wp-field-panel" :class="{ 'is-open': open }">
                                <button
                                    type="button"
                                    class="wp-field-panel__trigger"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                >
                                    <span>{{ __('locations.translation_edit.open') }}</span>
                                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                                </button>

                                <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                    <div class="wp-cluster wp-issue-description-row">
                                        <select
                                            class="wp-select wp-select--compact"
                                            wire:model.live="locationPreviewLocale"
                                            aria-label="{{ __('issues.show.description_language') }}"
                                        >
                                            @foreach ($locationTranslationLocales as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <label class="wp-field">
                                        <span class="wp-label">{{ __('locations.translation_edit.name') }}</span>
                                        <textarea class="wp-input" wire:model="locationTranslationName" rows="1"></textarea>
                                        @error('locationTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="wp-row">
                                        <button
                                            type="button"
                                            class="btn btn--ghost btn--sm"
                                            wire:click="saveLocationTranslationOverride"
                                            wire:loading.attr="disabled"
                                            wire:target="saveLocationTranslationOverride"
                                        >
                                            <span wire:loading wire:target="saveLocationTranslationOverride" class="wp-mr-2">
                                                <x-wp-spinner size="sm" />
                                            </span>
                                            <span>{{ __('locations.translation_edit.save') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
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

                @if ($editingCategoryId !== null)
                    <div class="wp-field" x-data="{ open: false }">
                        <span class="wp-label">{{ __('locations.categories.translation_edit.label') }}</span>

                        <div class="wp-field-panel" :class="{ 'is-open': open }">
                            <button
                                type="button"
                                class="wp-field-panel__trigger"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span>{{ __('locations.categories.translation_edit.open') }}</span>
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                            </button>

                            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                <div class="wp-cluster wp-issue-description-row">
                                    <select
                                        class="wp-select wp-select--compact"
                                        wire:model.live="categoryPreviewLocale"
                                        aria-label="{{ __('issues.show.description_language') }}"
                                    >
                                        @foreach ($categoryTranslationLocales as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('locations.categories.translation_edit.name') }}</span>
                                    <textarea class="wp-input" wire:model="categoryTranslationName" rows="1"></textarea>
                                    @error('categoryTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <div class="wp-row">
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="saveCategoryTranslationOverride"
                                        wire:loading.attr="disabled"
                                        wire:target="saveCategoryTranslationOverride"
                                    >
                                        <span wire:loading wire:target="saveCategoryTranslationOverride" class="wp-mr-2">
                                            <x-wp-spinner size="sm" />
                                        </span>
                                        <span>{{ __('locations.categories.translation_edit.save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="wp-field">
                    <x-wp-tooltip :text="__('locations.categories.allow_gps_location_hint')" wrap>
                        <label class="wp-check">
                            <input type="checkbox" wire:model="categoryAllowGpsLocation" />
                            <span>{{ __('locations.categories.fields.allow_gps_location') }}</span>
                        </label>
                    </x-wp-tooltip>
                    @error('categoryAllowGpsLocation') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <x-wp-tooltip :text="__('locations.categories.is_reservable_hint')" wrap>
                        <label class="wp-check">
                            <input type="checkbox" wire:model="categoryIsReservable" />
                            <span>{{ __('locations.categories.fields.is_reservable') }}</span>
                        </label>
                    </x-wp-tooltip>
                    @error('categoryIsReservable') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <x-wp-tooltip :text="__('locations.categories.allow_unit_checks_hint')" wrap>
                        <label class="wp-check">
                            <input type="checkbox" wire:model="categoryAllowUnitChecks" />
                            <span>{{ __('locations.categories.fields.allow_unit_checks') }}</span>
                        </label>
                    </x-wp-tooltip>
                    @error('categoryAllowUnitChecks') <p class="wp-error">{{ $message }}</p> @enderror
                </div>

                <div class="wp-field">
                    <x-wp-tooltip :text="__('locations.categories.require_reporter_contact_hint')" wrap>
                        <label class="wp-check">
                            <input type="checkbox" wire:model="categoryRequireReporterContact" />
                            <span>{{ __('locations.categories.fields.require_reporter_contact') }}</span>
                        </label>
                    </x-wp-tooltip>
                    @error('categoryRequireReporterContact') <p class="wp-error">{{ $message }}</p> @enderror
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
                                <span>{{ $team->localizedName() }}</span>
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
    @endif
</div>
