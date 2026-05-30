<div class="wp-card wp-card-pad wp-stack" wire:key="location-documents-{{ $location->id }}">
    <div class="wp-row">
        <div class="wp-grow wp-stack-tight">
            <div class="wp-cluster">
                <h2 class="wp-section-title">{{ __('locations.documents.title') }}</h2>
                <span class="wp-pill wp-pill--closed">{{ $documents->count() }}</span>
            </div>
            <p class="wp-muted">{{ __('locations.documents.subtitle') }}</p>
        </div>
        <div class="wp-cluster">
            <input type="search" class="wp-input" wire:model.live.debounce.300ms="search"
                   placeholder="{{ __('locations.documents.search_placeholder') }}">
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                {{ __('locations.documents.add') }}
            </button>
        </div>
    </div>

    @if (session('warning'))
        <div class="wp-flash wp-flash--warning">{{ session('warning') }}</div>
    @endif

    <div class="wp-list">
        @forelse ($documents as $document)
            <div class="wp-location-unit-row" wire:key="doc-{{ $document->id }}">
                <div class="wp-grow wp-stack-tight">
                    <p class="wp-issue-desc">{{ $document->description ?: __('locations.documents.no_description') }}</p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('locations.documents.unit_label') }}:
                        {{ $document->unit?->name ?? __('locations.documents.for_location') }}
                        · {{ $document->title }}
                        @if (! $document->is_active)
                            · <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                        @endif
                        @if ($document->is_public)
                            · {{ __('locations.documents.public') }}
                        @else
                            · {{ __('locations.documents.private') }}
                        @endif
                        @if ($document->requires_verification)
                            · {{ __('locations.documents.requires_verification') }}
                        @endif
                    </p>
                </div>
                <div class="wp-cluster">
                    <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path) }}" target="_blank" rel="noopener noreferrer"
                       class="btn btn--ghost btn--sm">{{ __('locations.documents.open') }}</a>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $document->id }})">
                        {{ __('common.button.edit') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleDocumentActive({{ $document->id }})">
                        {{ $document->is_active ? __('locations.documents.deactivate') : __('locations.documents.activate') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteDocument({{ $document->id }})"
                            wire:confirm="{{ __('locations.documents.confirm_delete') }}">
                        {{ __('common.button.delete') }}
                    </button>
                </div>
            </div>
        @empty
            <p class="wp-muted">{{ __('locations.documents.empty') }}</p>
        @endforelse
    </div>

    @if ($showCreateModal)
        <div class="wp-modal">
            <form wire:submit="createDocument" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.documents.add') }}</h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="closeCreateModal">{{ __('common.button.cancel') }}</button>
                </div>
                <p class="wp-muted">{{ __('locations.documents.modal_subtitle') }}</p>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.description') }}</span>
                    <textarea class="wp-input" rows="3" wire:model="description"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.link_unit') }}</span>
                    <select class="wp-select" wire:model="unitId">
                        <option value="">{{ __('locations.documents.for_location') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.file') }}</span>
                    <input type="file" class="wp-input" wire:model="documentFile">
                    <p class="wp-hint">{{ __('locations.documents.allowed_types_hint') }}</p>
                    @error('documentFile') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <div class="wp-stack-tight">
                    <label class="wp-check"><input type="checkbox" wire:model="isPublic"> {{ __('locations.documents.public') }}</label>
                    <label class="wp-check"><input type="checkbox" wire:model="requiresVerification"> {{ __('locations.documents.requires_verification') }}</label>
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.documents.active') }}</label>
                </div>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeCreateModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($showEditModal)
        <div class="wp-modal">
            <form wire:submit="updateDocument" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.documents.edit') }}</h2>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.description') }}</span>
                    <textarea class="wp-input" rows="3" wire:model="description"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.link_unit') }}</span>
                    <select class="wp-select" wire:model="unitId">
                        <option value="">{{ __('locations.documents.for_location') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <p class="wp-muted">{{ __('locations.documents.current_file', ['name' => $currentFileName]) }}</p>
                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.documents.replace_file') }}</span>
                    <input type="file" class="wp-input" wire:model="editDocumentFile">
                    <p class="wp-hint">{{ __('locations.documents.current_file_hint') }}</p>
                    @error('editDocumentFile') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <div class="wp-stack-tight">
                    <label class="wp-check"><input type="checkbox" wire:model="isPublic"> {{ __('locations.documents.public') }}</label>
                    <label class="wp-check"><input type="checkbox" wire:model="requiresVerification"> {{ __('locations.documents.requires_verification') }}</label>
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.documents.active') }}</label>
                </div>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
