<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <div class="wp-cluster">
                <h1 class="wp-page-title">{{ $location->name }}</h1>
                <div class="wp-cluster">
                    @if ($prevLocationId)
                        <a href="{{ route('locations.show', $prevLocationId) }}" class="btn btn--ghost btn--sm" aria-label="{{ __('locations.prev') }}">&lsaquo;</a>
                    @endif
                    @if ($nextLocationId)
                        <a href="{{ route('locations.show', $nextLocationId) }}" class="btn btn--ghost btn--sm" aria-label="{{ __('locations.next') }}">&rsaquo;</a>
                    @endif
                </div>
            </div>
            <p class="wp-muted">{{ __('locations.show_subtitle') }}</p>
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
                <a href="{{ route('locations.qr-pack', $location) }}" class="btn btn--ghost btn--sm">{{ __('locations.qr_pack_download') }}</a>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openBulkModal">{{ __('locations.bulk_add') }}</button>
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateUnit">{{ __('locations.units_add') }}</button>
            </div>
        </div>
        <p class="wp-muted">{{ __('locations.units_subtitle') }}</p>

        <div class="wp-list">
            @forelse ($units as $unit)
                @php
                    $canDelete = \App\Support\Units\UnitDeletionGuard::canDelete($unit);
                @endphp
                <div class="wp-location-unit-row" wire:key="unit-{{ $unit->id }}">
                    <div class="wp-grow">
                        <div class="wp-cluster">
                            <span class="wp-issue-desc">{{ $unit->name }}</span>
                            @if ($unit->defaultInternalTeam)
                                <span class="wp-pill wp-pill--progress">{{ $unit->defaultInternalTeam->name }}</span>
                            @endif
                            @if ($unit->hasOpenIssues())
                                <span class="wp-pill wp-pill--new">{{ __('locations.units.open_issue') }}</span>
                            @endif
                            @if (! $unit->is_active)
                                <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                            @endif
                        </div>
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

    @if ($showLocationModal)
        <div class="wp-modal">
            <form wire:submit="saveLocation" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.edit_title') }}</h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="$set('showLocationModal', false)">{{ __('common.button.cancel') }}</button>
                </div>
                @include('livewire.locations.partials.location-form-fields')
                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="$set('showLocationModal', false)">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('locations.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($showUnitModal)
        <div class="wp-modal">
            <form wire:submit="saveUnit" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ $editingUnitId ? __('locations.units.edit_title') : __('locations.units.create_title') }}</h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="$set('showUnitModal', false)">{{ __('common.button.cancel') }}</button>
                </div>
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.name') }}</span>
                    <input type="text" class="wp-input" wire:model="unitName" />
                    @error('unitName') <span class="wp-error">{{ $message }}</span> @enderror
                </label>
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.team') }}</span>
                    <select class="wp-input" wire:model="unitTeamId">
                        <option value="">{{ __('locations.units.no_team') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('unitTeamId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>
                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="$set('showUnitModal', false)">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('locations.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($showBulkModal)
        <div class="wp-modal">
            <form wire:submit="createBulk" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.bulk.title') }}</h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="$set('showBulkModal', false)">{{ __('common.button.cancel') }}</button>
                </div>

                <div class="wp-form-grid-2">
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.bulk.floors') }}</span>
                        <input type="number" min="1" class="wp-input" wire:model.live="bulkFloors" />
                    </label>
                    <label class="wp-field">
                        <span class="wp-label">{{ __('locations.bulk.rooms_per_floor') }}</span>
                        <input type="number" min="1" class="wp-input" wire:model.live="bulkRoomsPerFloor" />
                    </label>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.bulk.scheme') }}</span>
                    <select class="wp-input" wire:model.live="bulkScheme">
                        <option value="{{ \App\Support\Units\UnitBulkNaming::SCHEME_COMPACT_2 }}">{{ __('locations.bulk.scheme_compact') }}</option>
                        <option value="{{ \App\Support\Units\UnitBulkNaming::SCHEME_BLOCK_3 }}">{{ __('locations.bulk.scheme_block') }}</option>
                    </select>
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.bulk.prefix') }}</span>
                    <input type="text" class="wp-input" wire:model.live="bulkPrefix" />
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.units.fields.team') }}</span>
                    <select class="wp-input" wire:model="bulkTeamId">
                        <option value="">{{ __('locations.units.no_team') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
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
                    <button type="submit" class="btn btn--primary">{{ __('locations.bulk.submit') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
