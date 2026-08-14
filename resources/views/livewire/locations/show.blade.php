<div class="wp-stack" data-manual-capture="locations-show">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="locations"
                :title="$location->localizedName()"
                help-page="locations.show"
                :subtitle="__('locations.show_subtitle')"
            >
                <x-slot:toolbar>
                    <x-wp-detail-nav
                        route-name="locations.show"
                        :current-id="$location->id"
                        :nav-label="__('locations.nav_label')"
                        :first-id="$nav['firstId']"
                        :prev-id="$nav['prevId']"
                        :next-id="$nav['nextId']"
                        :last-id="$nav['lastId']"
                    />
                </x-slot:toolbar>
            </x-wp-page-head-title>
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
    @if (session('error'))
        <div class="wp-flash wp-flash--danger">{{ session('error') }}</div>
    @endif

    <x-wp-disclosure-card
        :title="__('locations.details_title')"
        :subtitle="$location->formattedAddress() ?: $location->notes"
    >
        @if ($location->formattedAddress())
            <p>{{ $location->formattedAddress() }}</p>
        @endif
        @if ($location->notes)
            <p class="wp-muted">{{ $location->notes }}</p>
        @endif
        <div class="wp-cluster">
            <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditLocation">{{ __('locations.edit') }}</button>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateLocation"
                    wire:confirm="{{ __('locations.confirm_deactivate') }}">{{ __('locations.deactivate') }}</button>
        </div>
    </x-wp-disclosure-card>

    <livewire:locations.documents :location="$location" />
    <livewire:locations.announcements :location="$location" />

    @if ($bulkSummaries->isNotEmpty())
        <x-wp-disclosure-card
            :title="__('locations.bulk.recent_title')"
            :subtitle="__('locations.bulk.recent_hint')"
            :count="$bulkSummaries->count()"
        >
            @foreach ($bulkSummaries as $summary)
                @php $batch = $summary['batch']; @endphp
                <div class="wp-row" wire:key="batch-{{ $batch->id }}">
                    <div class="wp-grow">
                        <p>
                            {{ $batch->created_at?->format('d-m-Y H:i') }}
                            &middot; {{ __('locations.bulk.batch_count', ['count' => $summary['total']]) }}
                            @if ($summary['first_name'] && $summary['last_name'])
                                &middot; {{ $summary['first_name'] }} – {{ $summary['last_name'] }}
                            @endif
                        </p>
                    </div>
                    @if ($summary['can_delete'])
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteBulkBatch({{ $batch->id }})"
                                wire:confirm="{{ __('locations.bulk.confirm_delete', ['count' => $summary['deletable']]) }}">
                            {{ __('locations.bulk.delete_batch', ['count' => $summary['deletable']]) }}
                        </button>
                    @endif
                </div>
            @endforeach
        </x-wp-disclosure-card>
    @endif

    @include('livewire.locations.import-history', ['batches' => $unitImportBatches])

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <div class="wp-cluster">
                <h2 class="wp-section-title">{{ __('locations.units_title') }}</h2>
                <span class="wp-pill wp-pill--closed">{{ __('locations.units_total', ['count' => $units->total()]) }}</span>
            </div>
            <div class="wp-cluster">
                @if ($units->total() > 0)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openQrPackModal">{{ __('locations.qr_pack_download') }}</button>
                @endif
                @if ($canImportUnitsCsv)
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openCsvImportModal">{{ __('locations.units_csv.button') }}</button>
                @endif
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openBulkModal">{{ __('locations.bulk_add') }}</button>
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateUnit">{{ __('locations.units_add') }}</button>
            </div>
        </div>
        <p class="wp-muted">{{ __('locations.units_subtitle') }}</p>
        <div class="wp-filter-row">
            <label class="wp-field">
                <span class="wp-label">{{ __('locations.units.filters.category') }}</span>
                <select class="wp-input" wire:model.live="unitCategoryFilter">
                    <option value="">{{ __('locations.units.filters.all_categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="wp-filter-row">
            <label class="wp-field">
                <span class="wp-label">{{ __('locations.units.filters.search') }}</span>
                <input type="search" class="wp-input" wire:model.live.debounce.300ms="unitSearch"
                       placeholder="{{ __('locations.units.filters.search_placeholder') }}" />
            </label>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($units as $unit)
                @php
                    $canDelete = \App\Support\Units\UnitDeletionGuard::canDelete($unit);
                @endphp
                <div @class(['wp-issue-row', 'wp-issue-row--focus' => $focusUnitId === $unit->id]) id="unit-row-{{ $unit->id }}" wire:key="unit-{{ $unit->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title wp-unit-title-row">
                            <span>{{ $unit->localizedName() }}</span>
                            @include('livewire.locations.partials.unit-gps-trigger', ['unit' => $unit, 'inline' => true])
                        </p>
                        @if ($unit->category || ($unit->category?->teams && $unit->category->teams->isNotEmpty()))
                            <p class="wp-issue-card-meta">
                                @if ($unit->category)
                                    {{ __('locations.units.meta_category', ['category' => $unit->category->localizedName()]) }}@if ($unit->category->teams && $unit->category->teams->isNotEmpty()), {{ __('locations.units.meta_team', ['team' => $unit->category->teams->first()->localizedName()]) }}@endif
                                @endif
                            </p>
                        @endif
                        @if ($unit->qrCodes && $unit->qrCodes->isNotEmpty())
                            <p class="wp-issue-card-meta">
                                {{ __('locations.units.meta_qr_linked', ['sticker' => $unit->qrCodes->first()->display_sticker_number]) }}
                            </p>
                        @endif
                    </div>
                    <div class="wp-issue-row-meta">
                        @if ($unit->hasOpenIssues())
                            <span class="wp-pill wp-pill--new">{{ __('locations.units.open_issue') }}</span>
                        @endif
                        @if (! $unit->is_active)
                            <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                        @endif
                    </div>
                    <div class="wp-cluster">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditUnit({{ $unit->id }})">{{ __('common.button.edit') }}</button>
                        @if ($unit->is_active)
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="deactivateUnit({{ $unit->id }})">{{ __('locations.deactivate') }}</button>
                        @else
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="activateUnit({{ $unit->id }})">{{ __('locations.activate') }}</button>
                        @endif
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteUnit({{ $unit->id }})"
                                @disabled(! $canDelete)>{{ __('common.button.delete') }}</button>
                        <a href="{{ route('units.qr', $unit) }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('locations.unit_qr') }}</a>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openUnitQrPackModal({{ $unit->id }})">{{ __('locations.unit_qr_pack.button') }}</button>
                        @if ($hasEsgModule && in_array($unit->id, $unitIdsWithEsgMeasurements, true))
                            <a href="{{ route('esg.point.history', ['unit' => $unit->id]) }}" class="btn btn--ghost btn--sm">{{ __('esg.point.link') }}</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('locations.no_units') }}</p>
            @endforelse
        </div>

        @if ($units->hasPages())
            {{ $units->links() }}
        @endif

        @if ($focusUnitId)
            <div
                hidden
                x-data
                x-init="$nextTick(() => document.getElementById('unit-row-{{ $focusUnitId }}')?.scrollIntoView({ block: 'center', behavior: 'smooth' }))"
                aria-hidden="true"
            ></div>
        @endif
    </div>

    <livewire:locations.unit-gps-history-modal />

    @if ($showLocationModal)
        <x-wp-modal closeMethod="closeLocationModal" aria-labelledby="location-edit-title">
            <form wire:submit="saveLocation" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="location-edit-title" class="wp-section-title">{{ __('locations.edit_title') }}</h2>
                    <x-wp-modal-close wire:click="closeLocationModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    @include('livewire.locations.partials.location-form-fields')

                    @if ($location->is_active)
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
                    <button type="button" class="btn btn--ghost" wire:click="closeLocationModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="saveLocation">
                        <span wire:loading wire:target="saveLocation" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ __('locations.save') }}</span>
                    </button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showUnitModal)
        <x-wp-modal closeMethod="closeUnitModal">
            <form
                x-data
                x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
                @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.saveUnit()"
                class="wp-card wp-card-pad wp-stack wp-modal-card"
            >
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ $editingUnitId ? __('locations.units.edit_title') : __('locations.units.create_title') }}</h2>
                    <x-wp-modal-close wire:click="$set('showUnitModal', false)" />
                </div>
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.name') }}</span>
                    <input type="text" class="wp-input" wire:model="unitName" />
                    @error('unitName') <span class="wp-error">{{ $message }}</span> @enderror
                </label>
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.description') }}</span>
                    <textarea class="wp-input" wire:model="unitDescription" rows="1"></textarea>
                    @error('unitDescription') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                @if ($editingUnitId && $previewUnit?->is_active)
                    <div class="wp-field" x-data="{ open: false }">
                        <span class="wp-label">{{ __('locations.units.translation_edit.label') }}</span>

                        <div class="wp-field-panel" :class="{ 'is-open': open }">
                            <button
                                type="button"
                                class="wp-field-panel__trigger"
                                @click="open = !open"
                                :aria-expanded="open"
                            >
                                <span>{{ __('locations.units.translation_edit.open') }}</span>
                                <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                            </button>

                            <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                <div class="wp-cluster wp-issue-description-row">
                                    <select
                                        class="wp-select wp-select--compact"
                                        wire:model.live="previewLocale"
                                        aria-label="{{ __('issues.show.description_language') }}"
                                    >
                                        @foreach ($descriptionLocales as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('locations.units.translation_edit.name') }}</span>
                                    <textarea class="wp-input" wire:model="unitTranslationName" rows="1"></textarea>
                                    @error('unitTranslationName') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <label class="wp-field">
                                    <span class="wp-label">{{ __('locations.units.translation_edit.description') }}</span>
                                    <textarea class="wp-input" wire:model="unitTranslationDescription" rows="2"></textarea>
                                    @error('unitTranslationDescription') <span class="wp-error">{{ $message }}</span> @enderror
                                </label>

                                <div class="wp-row">
                                    <button
                                        type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="saveUnitTranslationOverride"
                                        wire:loading.attr="disabled"
                                        wire:target="saveUnitTranslationOverride"
                                    >
                                        <span wire:loading wire:target="saveUnitTranslationOverride" class="wp-mr-2">
                                            <x-wp-spinner size="sm" />
                                        </span>
                                        <span>{{ __('locations.units.translation_edit.save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.category') }}</span>
                    <select class="wp-input" wire:model.live="unitCategoryId">
                        <option value="">{{ __('locations.units.no_category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                        @endforeach
                    </select>
                    @error('unitCategoryId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                @if ($hasApiAccess)
                    <x-wp-tooltip :text="__('locations.units.external_id_hint')" wrap class="wp-tooltip--block">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.units.fields.external_id') }}</span>
                            <input type="text" class="wp-input" wire:model="unitExternalId" maxlength="100" autocomplete="off">
                            @error('unitExternalId') <span class="wp-error">{{ $message }}</span> @enderror
                        </label>
                    </x-wp-tooltip>
                @endif

                <label class="wp-check wp-check--boxed">
                    <input type="checkbox" wire:model="unitPublicReportsEnabled">
                    <span>{{ __('locations.units.fields.public_reports_enabled') }}</span>
                </label>

                <x-wp-tooltip :text="__('locations.units.allow_reservations_hint')" wrap class="wp-tooltip--block">
                    <label class="wp-check wp-check--boxed">
                        <input type="checkbox" wire:model="unitAllowReservations">
                        <span>{{ __('locations.units.fields.allow_reservations') }}</span>
                    </label>
                </x-wp-tooltip>

                <x-wp-tooltip :text="__('locations.units.allow_unit_checks_hint')" wrap class="wp-tooltip--block">
                    <label class="wp-check wp-check--boxed">
                        <input type="checkbox" wire:model.live="unitAllowUnitChecks">
                        <span>{{ __('locations.units.fields.allow_unit_checks') }}</span>
                    </label>
                </x-wp-tooltip>

                @if ($unitAllowUnitChecks)
                    <x-wp-tooltip :text="__('locations.units.check_list_hint')" wrap class="wp-tooltip--block">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.units.fields.check_list') }}</span>
                            <select class="wp-input" wire:model="unitCheckListId">
                                <option value="">{{ __('locations.units.no_check_list') }}</option>
                                @foreach ($unitCheckLists as $checkList)
                                    <option value="{{ $checkList->id }}">{{ $checkList->localizedName() }}</option>
                                @endforeach
                            </select>
                            @error('unitCheckListId') <span class="wp-error">{{ $message }}</span> @enderror
                        </label>
                    </x-wp-tooltip>
                @endif

                <x-wp-tooltip :text="__('locations.units.require_reporter_contact_hint')" wrap class="wp-tooltip--block">
                    <label class="wp-check wp-check--boxed">
                        <input type="checkbox" wire:model="unitRequireReporterContact">
                        <span>{{ __('locations.units.fields.require_reporter_contact') }}</span>
                    </label>
                </x-wp-tooltip>

                @if ($editingUnitId && $this->editingUnit)
                    @php
                        $storedCount = $this->editingUnit?->qrLinkPhotos->count() ?? 0;
                        $tempCount = count($unitPhotos);
                        $totalCount = $storedCount + $tempCount;
                        $canAddMore = $totalCount < 4;
                    @endphp

                    <div class="wp-field" wire:key="unit-photos-section-{{ $totalCount }}">
                        <label class="wp-label">{{ __('locations.units.edit.photos_label') }}</label>

                        @if ($totalCount > 0)
                            <div
                                class="wp-photo-gallery"
                                x-data="{ lightboxSrc: null }"
                                @keydown.escape.window="lightboxSrc = null"
                            >
                                <div class="wp-photo-grid wp-photo-grid--gallery">
                                    @foreach ($this->editingUnit->qrLinkPhotos as $photo)
                                        @if ($photo->hasPublicFile())
                                            <div class="wp-photo-thumb" wire:key="qr-photo-{{ $photo->id }}">
                                                <button
                                                    type="button"
                                                    style="background:none;border:none;padding:0;width:100%;height:100%;"
                                                    @click="lightboxSrc = @js($photo->publicUrl())"
                                                    aria-label="{{ __('issues.show.photo_enlarge') }}"
                                                >
                                                    <img src="{{ $photo->publicUrl() }}" alt="" width="80" height="80" loading="lazy" x-on:error="$el.closest('.wp-photo-thumb')?.remove()">
                                                </button>
                                                <button
                                                    type="button"
                                                    class="wp-photo-remove"
                                                    wire:click="removeUnitPhoto({{ $photo->id }})"
                                                    wire:confirm="{{ __('locations.units.edit.delete_photo_confirm') }}"
                                                >×</button>
                                            </div>
                                        @endif
                                    @endforeach

                                    @foreach ($unitPhotos as $index => $photo)
                                        <div class="wp-photo-thumb" wire:key="temp-photo-{{ $index }}">
                                            <button
                                                type="button"
                                                style="background:none;border:none;padding:0;width:100%;height:100%;"
                                                @click="lightboxSrc = @js($photo->temporaryUrl())"
                                                aria-label="{{ __('issues.show.photo_enlarge') }}"
                                            >
                                                <img src="{{ $photo->temporaryUrl() }}" alt="" width="80" height="80" loading="lazy">
                                            </button>
                                            <button
                                                type="button"
                                                class="wp-photo-remove"
                                                wire:click="removeUnitTempPhoto({{ $index }})"
                                            >×</button>
                                        </div>
                                    @endforeach
                                </div>

                                <div
                                    class="wp-photo-lightbox"
                                    x-show="lightboxSrc"
                                    x-cloak
                                    x-transition.opacity
                                    role="dialog"
                                    aria-modal="true"
                                    @click="lightboxSrc = null"
                                >
                                    <img :src="lightboxSrc" alt="" @click.stop>
                                </div>
                            </div>
                        @endif

                        @if ($canAddMore)
                            @include('partials.wp-issue-photo-upload', ['model' => 'unitPhotos'])
                        @endif

                        @error('unitPhotos') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('unitPhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-hint">{{ __('locations.units.edit.photos_hint') }}</p>
                    </div>
                @endif

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="$set('showUnitModal', false)">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="saveUnit">
                        <span wire:loading wire:target="saveUnit" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ __('common.button.save') }}</span>
                    </button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showBulkModal)
        <x-wp-modal closeMethod="closeBulkModal">
            <form
                class="wp-card wp-card-pad wp-stack wp-modal-card"
                x-data="wpBulkUnitRanges({
                    initialRanges: @js($bulkRanges),
                    categoryId: @js($bulkCategoryId),
                    maxUnits: {{ \App\Actions\Locations\BulkCreateUnitsAction::MAX_UNITS }},
                    i18n: {
                        batchCount: @js(__('locations.bulk.batch_count')),
                        submitCount: @js(__('locations.bulk.submit_count')),
                        duplicatesCount: @js(__('locations.bulk.errors.duplicates_count')),
                        previewEmpty: @js(__('locations.bulk.preview_empty')),
                    },
                })"
                @submit.prevent="submit($wire)"
            >
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.bulk.title') }}</h2>
                    <x-wp-modal-close wire:click="closeBulkModal" />
                </div>

                <div class="wp-card wp-card-pad wp-surface-2 wp-stack">
                    <div class="wp-form-grid-2">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.bulk.start') }}</span>
                            <input
                                type="text"
                                inputmode="numeric"
                                class="wp-input"
                                x-model="range.start"
                                placeholder="{{ __('locations.bulk.start_hint') }}"
                            />
                        </label>
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.bulk.count') }}</span>
                            <input
                                type="number"
                                min="1"
                                max="500"
                                class="wp-input"
                                x-model="range.count"
                            />
                        </label>
                    </div>

                    <div class="wp-form-grid-2">
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.bulk.padding') }}</span>
                            <input
                                type="number"
                                min="1"
                                max="20"
                                class="wp-input"
                                x-model="range.padding"
                                placeholder="{{ __('locations.bulk.padding_auto') }}"
                            />
                        </label>
                        <label class="wp-field">
                            <span class="wp-label">{{ __('locations.bulk.prefix') }}</span>
                            <input
                                type="text"
                                class="wp-input"
                                x-model="range.prefix"
                                placeholder="{{ __('locations.bulk.prefix_hint') }}"
                                maxlength="30"
                            />
                        </label>
                    </div>

                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.bulk.suffix') }}</span>
                        <input
                            type="text"
                            class="wp-input"
                            x-model="range.suffix"
                            placeholder="{{ __('locations.bulk.suffix_hint') }}"
                            maxlength="30"
                        />
                    </label>
                </div>

                <div class="wp-card wp-card-pad wp-surface-2">
                    <p class="wp-label">
                        {{ __('locations.bulk.preview') }}
                        <span x-text="'(' + batchCountLabel(preview.total) + ')'"></span>
                    </p>
                    <p class="wp-muted" x-show="preview.previewNames.length === 0" x-text="i18n.previewEmpty"></p>
                    <p class="wp-muted" x-show="preview.previewNames.length > 0">
                        <template x-for="(name, i) in preview.previewNames" :key="i">
                            <span>
                                <span x-show="preview.truncated && i === preview.previewNames.length - 1">
                                    <span x-show="i > 0">, </span>…,
                                </span>
                                <span x-show="!(preview.truncated && i === preview.previewNames.length - 1) && i > 0">, </span>
                                <span
                                    x-text="name"
                                    :class="{ 'wp-error': preview.duplicates.includes(name) }"
                                ></span>
                            </span>
                        </template>
                    </p>
                    <p
                        class="wp-error"
                        x-show="preview.hasDuplicates"
                        x-text="duplicatesLabel(preview.duplicates.length)"
                    ></p>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.category') }}</span>
                    <select class="wp-input" x-model="categoryId">
                        <option value="">{{ __('locations.units.no_category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->localizedName() }}</option>
                        @endforeach
                    </select>
                </label>

                @error('bulkRanges') <span class="wp-error">{{ $message }}</span> @enderror
                @error('bulkRanges.0.start') <span class="wp-error">{{ $message }}</span> @enderror

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeBulkModal">{{ __('common.button.cancel') }}</button>
                    <button
                        type="submit"
                        class="btn btn--primary"
                        :disabled="!canSubmit"
                        wire:loading.attr="disabled"
                        wire:target="createBulk"
                    >
                        <span wire:loading wire:target="createBulk" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span x-text="submitLabel(preview.total)"></span>
                    </button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showCsvImportModal)
        <x-wp-modal closeMethod="closeCsvImportModal" aria-labelledby="location-units-csv-title">
            <div class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="location-units-csv-title" class="wp-section-title">{{ __('locations.units_csv.title') }}</h2>
                    <x-wp-modal-close wire:click="closeCsvImportModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('locations.units_csv.hint', ['location' => $location->localizedName()]) }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="location-units-csv-file">{{ __('locations.import_file_label') }}</label>
                        <div class="wp-cluster">
                            <input type="file" id="location-units-csv-file" class="wp-input wp-grow" wire:model="csvImportFile" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadLocationUnitsSampleCsv">
                                {{ __('locations.import_sample.download_sample_csv') }}
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="downloadLocationUnitsSampleXlsx">
                                {{ __('locations.import_sample.download_sample_xlsx') }}
                            </button>
                        </div>
                        @error('csvImportFile') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($csvImportErrors !== [])
                        <div class="wp-flash wp-flash--danger">
                            <ul class="wp-form-error-list">
                                @foreach ($csvImportErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeCsvImportModal">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="importUnitsCsv" wire:loading.attr="disabled" wire:target="importUnitsCsv,csvImportFile" :disabled="$csvImportFile === null">
                        <x-wp-spinner wire:loading wire:target="importUnitsCsv,csvImportFile" class="wp-mr-2" />
                        <span wire:loading.remove wire:target="importUnitsCsv,csvImportFile">{{ __('locations.import_submit') }}</span>
                        <span wire:loading wire:target="importUnitsCsv,csvImportFile">{{ __('locations.import_submit_loading') }}</span>
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showQrPackModal)
        <x-wp-modal closeMethod="closeQrPackModal" aria-labelledby="qr-pack-modal-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="qr-pack-modal-title" class="wp-section-title">{{ __('locations.qr_pack.modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeQrPackModal" />
                </div>

                <p class="wp-muted">{{ __('locations.qr_pack.modal_subtitle') }}</p>

                <div class="wp-card-section">
                    <div class="wp-row wp-row--align-center">
                        <label class="wp-label wp-label--checkbox">
                            <input type="checkbox" wire:model.live="qrPackGenerateDynamic">
                            <span>{{ __('locations.qr_pack.generate_dynamic') }}</span>
                        </label>
                        <x-wp-page-help page="locations.show" />
                    </div>

                    @if ($qrPackGenerateDynamic)
                        <div class="wp-stack-tight">
                            <label class="wp-label" for="qr-pack-dynamic-count">
                                {{ __('locations.qr_pack.dynamic_count_label') }}
                            </label>
                            <input
                                id="qr-pack-dynamic-count"
                                type="number"
                                class="wp-input"
                                wire:model.live="qrPackDynamicCount"
                                min="1"
                                max="100"
                                value="15"
                            >
                            <p class="wp-muted wp-text-sm">{{ __('locations.qr_pack.dynamic_count_help') }}</p>
                        </div>
                    @endif
                </div>

                <div
                    class="wp-list wp-list--entity-rows"
                    x-data="{
                        downloading: null,
                        error: null,
                        async download(url, key) {
                            if (this.downloading) {
                                return;
                            }

                            this.downloading = key;
                            this.error = null;

                            try {
                                await window.wpDownloadQrPackUrl(url);
                            } catch (exception) {
                                this.error = exception?.message || @js(__('locations.qr_pack.download_failed'));
                            } finally {
                                this.downloading = null;
                            }
                        },
                    }"
                >
                    @foreach ($qrPackTemplates as $template)
                        @php
                            $qrPackDownloadUrl = route('locations.qr-pack', [
                                'location' => $location,
                                'template' => $template->value,
                                'dynamic' => $qrPackGenerateDynamic ? '1' : null,
                                'count' => $qrPackGenerateDynamic ? $qrPackDynamicCount : null,
                            ]);
                        @endphp
                        <button
                            type="button"
                            class="wp-issue-row"
                            wire:key="qr-pack-format-{{ $template->value }}"
                            @click="download(@js($qrPackDownloadUrl), @js($template->value))"
                            :disabled="downloading !== null"
                            :aria-busy="downloading === @js($template->value)"
                        >
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ __('locations.qr_pack.formats.'.$template->value.'.title') }}</p>
                                <p class="wp-muted" x-show="downloading !== @js($template->value)">{{ __('locations.qr_pack.formats.'.$template->value.'.description') }}</p>
                                <p class="wp-muted wp-cluster" x-show="downloading === @js($template->value)" x-cloak>
                                    <x-wp-spinner size="sm" :visible="true" />
                                    <span>{{ __('locations.qr_pack.generating') }}</span>
                                </p>
                            </div>
                        </button>
                    @endforeach

                    <p class="wp-error" x-show="error" x-text="error" x-cloak></p>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($showUnitQrPackModal && $unitQrPackUnit)
        <x-wp-modal closeMethod="closeUnitQrPackModal" aria-labelledby="unit-qr-pack-modal-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="unit-qr-pack-modal-title" class="wp-section-title">{{ __('locations.unit_qr_pack.modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeUnitQrPackModal" />
                </div>

                <p class="wp-muted">{{ __('locations.unit_qr_pack.modal_subtitle', ['name' => $unitQrPackUnit->name]) }}</p>

                <div
                    class="wp-list wp-list--entity-rows"
                    x-data="{
                        downloading: null,
                        error: null,
                        async download(url, key) {
                            if (this.downloading) {
                                return;
                            }

                            this.downloading = key;
                            this.error = null;

                            try {
                                await window.wpDownloadQrPackUrl(url);
                            } catch (exception) {
                                this.error = exception?.message || @js(__('locations.unit_qr_pack.download_failed'));
                            } finally {
                                this.downloading = null;
                            }
                        },
                    }"
                >
                    @foreach ($unitQrPackTemplates as $template)
                        @php
                            $unitQrPackDownloadUrl = route('units.qr-pack', [
                                'unit' => $unitQrPackUnit,
                                'template' => $template->value,
                            ]);
                        @endphp
                        <button
                            type="button"
                            class="wp-issue-row"
                            wire:key="unit-qr-pack-format-{{ $unitQrPackUnit->id }}-{{ $template->value }}"
                            @click="download(@js($unitQrPackDownloadUrl), @js($template->value))"
                            :disabled="downloading !== null"
                            :aria-busy="downloading === @js($template->value)"
                        >
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ __('locations.qr_pack.formats.'.$template->value.'.title') }}</p>
                                <p class="wp-muted" x-show="downloading !== @js($template->value)">{{ __('locations.qr_pack.formats.'.$template->value.'.description') }}</p>
                                <p class="wp-muted wp-cluster" x-show="downloading === @js($template->value)" x-cloak>
                                    <x-wp-spinner size="sm" :visible="true" />
                                    <span>{{ __('locations.unit_qr_pack.generating') }}</span>
                                </p>
                            </div>
                        </button>
                    @endforeach

                    <p class="wp-error" x-show="error" x-text="error" x-cloak></p>
                </div>
            </div>
        </x-wp-modal>
    @endif

</div>
