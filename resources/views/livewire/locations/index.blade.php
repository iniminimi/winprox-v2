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
            <button type="button" @class(['btn', 'btn--primary', 'wp-btn--prio-pulse' => $pulseAddLocationButton]) wire:click="openCreate">
                {{ __('locations.add') }}
            </button>
        </div>
    </div>

    @if (session('success'))
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
        @else
            <p class="wp-muted">{{ __('locations.categories.empty') }}</p>
        @endif
    </x-wp-disclosure-card>

    <x-wp-disclosure-card
        :title="__('unit_checks.lists.title')"
        :subtitle="__('locations.checklists.click_to_manage')"
        :count="$checkLists->count()"
        entangle="showCheckListsSection"
    >
        <x-slot:toolbar>
            @can('create', App\Models\UnitCheckList::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateCheckList">
                    {{ __('unit_checks.lists.create') }}
                </button>
            @endcan
        </x-slot:toolbar>

        @if ($checkLists->isNotEmpty())
            <div class="wp-list wp-list--entity-rows">
                @foreach ($checkLists as $list)
                    <div class="wp-issue-row" wire:key="unit-check-list-{{ $list->id }}">
                        <div class="wp-grow wp-stack-tight">
                            <div class="wp-cluster wp-cluster--wrap">
                                <p class="wp-issue-card-title">{{ $list->name }}</p>
                                @if (! $list->is_active)
                                    <span class="wp-pill wp-pill--closed">{{ __('unit_checks.lists.inactive') }}</span>
                                @endif
                            </div>
                            <p class="wp-muted wp-text-sm">
                                {{ trans_choice('unit_checks.lists.item_count', $list->items_count, ['count' => $list->items_count]) }}
                            </p>
                        </div>
                        <div class="wp-cluster">
                            @can('update', $list)
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditCheckList({{ $list->id }})">
                                    {{ __('common.button.edit') }}
                                </button>
                            @endcan
                            @can('delete', $list)
                                @if ($list->is_active)
                                    <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateCheckList({{ $list->id }})">
                                        {{ __('unit_checks.lists.deactivate') }}
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="wp-muted">{{ __('unit_checks.lists.empty') }}</p>
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

    @if ($showCheckListModal)
        <x-wp-modal closeMethod="closeCheckListModal">
            <form wire:submit="saveCheckList" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">
                        {{ $editingCheckListId ? __('unit_checks.lists.edit_title') : __('unit_checks.lists.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeCheckListModal" />
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('unit_checks.lists.fields.name') }}</span>
                    <input type="text" class="wp-input" wire:model="checkListName" />
                    @error('checkListName') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('unit_checks.lists.fields.items') }}</span>
                    <textarea class="wp-textarea" rows="6" wire:model="checkListItemsText" placeholder="{{ __('unit_checks.lists.fields.items_ph') }}"></textarea>
                    <span class="wp-muted wp-text-sm">{{ __('unit_checks.lists.fields.items_hint') }}</span>
                    @error('checkListItemsText') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field wp-cluster">
                    <input type="checkbox" wire:model="checkListIsActive" />
                    <span>{{ __('unit_checks.lists.fields.active') }}</span>
                </label>

                <div class="wp-cluster wp-cluster--end">
                    <button type="button" class="btn btn--ghost" wire:click="closeCheckListModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
    @endif
</div>
