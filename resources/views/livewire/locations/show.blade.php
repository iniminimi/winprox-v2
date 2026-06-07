<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="locations"
                :title="$location->name"
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
        <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost">{{ __('dashboard.briefing_print') }}</a>
    </div>

    @if (session('success'))
        <div class="wp-flash wp-flash--success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="wp-flash wp-flash--danger">{{ session('error') }}</div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('locations.details_title') }}</h2>
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
            <a href="{{ route('locations.qr', $location) }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('locations.location_qr') }}</a>
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <div class="wp-cluster">
                <h2 class="wp-section-title">{{ __('locations.units_title') }}</h2>
                <span class="wp-pill wp-pill--closed">{{ __('locations.units_total', ['count' => $units->count()]) }}</span>
            </div>
            <div class="wp-cluster">
                @if ($units->isNotEmpty())
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openQrPackModal">{{ __('locations.qr_pack_download') }}</button>
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
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
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
                <div class="wp-issue-row" wire:key="unit-{{ $unit->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $unit->name }}</p>
                        <div class="wp-issue-card-meta">
                            @if ($unit->category)
                                {{ __('locations.units.meta_category', ['category' => $unit->category->name]) }}
                            @endif
                            @if ($unit->category && $unit->category->teams && $unit->category->teams->isNotEmpty())
                                {{ $unit->category ? ', ' : '' }}{{ __('locations.units.meta_team', ['team' => $unit->category->teams->first()->name]) }}
                            @endif
                            @if ($unit->qrCodes && $unit->qrCodes->isNotEmpty())
                                {{ ($unit->category || ($unit->category && $unit->category->teams && $unit->category->teams->isNotEmpty())) ? ', ' : '' }}{{ __('qr.connect.linked_qr') }} : {{ $unit->qrCodes->first()->sticker_number }}
                            @endif
                            @if ($unit->hasGps())
                                <a href="{{ $unit->googleMapsUrl() }}" target="_blank" rel="noopener" class="wp-muted" style="margin-left:0.5rem;vertical-align:middle;display:inline-flex;align-items:center;gap:0.25rem;">
                                    <svg style="width:0.875rem;height:0.875rem;" fill="#EA4335" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    <span>GPS</span>
                                </a>
                            @endif
                        </div>
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
                        @endif
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteUnit({{ $unit->id }})"
                                @disabled(! $canDelete)>{{ __('common.button.delete') }}</button>
                        <a href="{{ route('units.qr', $unit) }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('locations.unit_qr') }}</a>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('locations.no_units') }}</p>
            @endforelse
        </div>
    </div>

    @if ($bulkSummaries->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('locations.bulk.recent_title') }}</h2>
            <p class="wp-muted">{{ __('locations.bulk.recent_hint') }}</p>
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
        </div>
    @endif

    <livewire:locations.documents :location="$location" />
    <livewire:locations.announcements :location="$location" />

    @if ($showLocationModal)
        <x-wp-modal closeMethod="closeLocationModal" aria-labelledby="location-edit-title">
            <form wire:submit="saveLocation" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="location-edit-title" class="wp-section-title">{{ __('locations.edit_title') }}</h2>
                    <x-wp-modal-close wire:click="closeLocationModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    @include('livewire.locations.partials.location-form-fields')
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
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.category') }}</span>
                    <select class="wp-input" wire:model="unitCategoryId">
                        <option value="">{{ __('locations.units.no_category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('unitCategoryId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                @if ($editingUnitId)
                    {{-- Google Maps link when GPS exists --}}
                    @if ($this->editingUnit?->hasGps())
                        <a href="{{ $this->editingUnit->googleMapsUrl() }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm" style="justify-content:center;align-items:center;gap:0.5rem;">
                            <svg style="width:1rem;height:1rem;" fill="#EA4335" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            {{ __('portal.worker.navigate_to_location') }}
                        </a>
                    @endif

                    @php
                        $storedCount = $this->editingUnit?->qrLinkPhotos->count() ?? 0;
                        $tempCount = count($unitPhotos);
                        $totalCount = $storedCount + $tempCount;
                        $canAddMore = $totalCount < 4;
                    @endphp

                    <div class="wp-field" wire:key="unit-photos-section-{{ $totalCount }}">
                        <label class="wp-label">{{ __('locations.units.edit.photos_label') }}</label>

                        @if ($totalCount > 0)
                            <div class="wp-photo-grid wp-photo-grid--gallery">
                                @foreach ($this->editingUnit->qrLinkPhotos as $photo)
                                    @if ($photo->hasPublicFile())
                                        <div class="wp-photo-thumb" style="position:relative;" wire:key="qr-photo-{{ $photo->id }}">
                                            <img src="{{ $photo->publicUrl() }}" alt="" width="80" height="80" loading="lazy">
                                            <button
                                                type="button"
                                                class="btn btn--danger btn--sm"
                                                style="position:absolute;top:2px;right:2px;padding:2px 6px;font-size:10px;"
                                                wire:click="removeUnitPhoto({{ $photo->id }})"
                                                wire:confirm="{{ __('locations.units.edit.delete_photo_confirm') }}"
                                            >×</button>
                                        </div>
                                    @endif
                                @endforeach

                                @foreach ($unitPhotos as $index => $photo)
                                    <div class="wp-photo-thumb" style="position:relative;" wire:key="temp-photo-{{ $index }}">
                                        <img src="{{ $photo->temporaryUrl() }}" alt="" width="80" height="80" loading="lazy">
                                        <button
                                            type="button"
                                            class="btn btn--danger btn--sm"
                                            style="position:absolute;top:2px;right:2px;padding:2px 6px;font-size:10px;"
                                            wire:click="removeUnitTempPhoto({{ $index }})"
                                        >×</button>
                                    </div>
                                @endforeach
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
            <form wire:submit="createBulk" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.bulk.title') }}</h2>
                    <x-wp-modal-close wire:click="$set('showBulkModal', false)" />
                </div>

                <div class="wp-form-grid-2">
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.bulk.floors') }}</span>
                        <input type="number" min="1" class="wp-input" wire:model.live.debounce.300ms="bulkFloors" />
                    </label>
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.bulk.rooms_per_floor') }}</span>
                        <input type="number" min="1" max="{{ $this->bulkRoomsMax }}" class="wp-input" wire:model.live.debounce.300ms="bulkRoomsPerFloor" placeholder="{{ __('locations.bulk.rooms_per_floor_hint') }}" />
                    </label>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.bulk.scheme') }}</span>
                    <select class="wp-input" wire:model.live.debounce.300ms="bulkScheme">
                        <option value="{{ \App\Support\Units\UnitBulkNaming::SCHEME_COMPACT_2 }}">{{ __('locations.bulk.scheme_compact') }}</option>
                        <option value="{{ \App\Support\Units\UnitBulkNaming::SCHEME_BLOCK_3 }}">{{ __('locations.bulk.scheme_block') }}</option>
                    </select>
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.bulk.prefix') }}</span>
                    <input type="text" class="wp-input" wire:model.live.debounce.500ms="bulkPrefix" maxlength="30" />
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.category') }}</span>
                    <select class="wp-input" wire:model="bulkCategoryId">
                        <option value="">{{ __('locations.units.no_category') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($bulkPreview !== [])
                    <div class="wp-card wp-card-pad wp-surface-2">
                        <p class="wp-label">{{ __('locations.bulk.preview') }}</p>
                        <p class="wp-muted">{{ implode(', ', $bulkPreview) }}</p>
                    </div>
                @endif

                @error('bulkFloors') <span class="wp-error">{{ $message }}</span> @enderror

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="$set('showBulkModal', false)">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="createBulk">
                        <span wire:loading wire:target="createBulk" class="wp-mr-2">
                            <x-wp-spinner size="sm" />
                        </span>
                        <span>{{ __('locations.bulk.submit') }}</span>
                    </button>
                </div>
            </form>
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

                <div class="wp-list wp-list--entity-rows">
                    @foreach (\App\Support\Qr\QrStickerSheetTemplate::cases() as $template)
                        <a href="{{ route('locations.qr-pack', ['location' => $location, 'template' => $template->value, 'dynamic' => $qrPackGenerateDynamic ? '1' : null, 'count' => $qrPackGenerateDynamic ? $qrPackDynamicCount : null]) }}"
                           class="wp-issue-row"
                           wire:key="qr-pack-format-{{ $template->value }}">
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ __('locations.qr_pack.formats.'.$template->value.'.title') }}</p>
                                <p class="wp-muted">{{ __('locations.qr_pack.formats.'.$template->value.'.description') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </x-wp-modal>
    @endif

</div>
