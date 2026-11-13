<div wire:key="team-documents-{{ $team->id }}">
    <x-wp-disclosure-card
        :title="__('team.library.documents_title')"
        :subtitle="__('team.library.documents_subtitle')"
        :count="$documents->count()"
    >
        <x-slot:toolbar>
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                {{ __('team.library.add_document') }}
            </button>
        </x-slot:toolbar>

        <div class="wp-list">
            @forelse ($documents as $document)
                <div class="wp-issue-row" wire:key="team-doc-{{ $document->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-desc">{{ $document->description }}</p>
                        <p class="wp-muted wp-text-sm">
                            {{ $document->title }}
                            · {{ $document->worker?->displayName() ?? __('team.library.audience_team') }}
                            @unless ($document->is_active)
                                · <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                            @endunless
                        </p>
                    </div>
                    <div class="wp-cluster">
                        <a href="{{ route('team-documents.file', $document) }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">
                            {{ __('team.library.open') }}
                        </a>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $document->id }})">
                            {{ __('common.button.edit') }}
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteDocument({{ $document->id }})"
                                wire:confirm="{{ __('team.library.confirm_delete_document') }}">
                            {{ __('common.button.delete') }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('team.library.empty_documents') }}</p>
            @endforelse
        </div>
    </x-wp-disclosure-card>

    @if ($showCreateModal)
        <x-wp-modal closeMethod="closeCreateModal" aria-labelledby="team-document-create-title">
            <form wire:submit="createDocument" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="team-document-create-title" class="wp-section-title">{{ __('team.library.add_document') }}</h2>
                    <x-wp-modal-close wire:click="closeCreateModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-document-description">{{ __('team.library.description') }}</label>
                    <textarea id="team-document-description" class="wp-input" rows="3" wire:model="description" maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-document-file">{{ __('team.library.file') }}</label>
                    <input id="team-document-file" type="file" class="wp-input" wire:model="documentFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    @error('documentFile') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                @include('livewire.teams.partials.audience-fields', ['formId' => 'create-doc', 'workers' => $workers, 'audience' => $audience])
                <label class="wp-check">
                    <input type="checkbox" wire:model="isActive">
                    <span>{{ __('team.library.active') }}</span>
                </label>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeCreateModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showEditModal)
        <x-wp-modal closeMethod="closeEditModal" aria-labelledby="team-document-edit-title">
            <form wire:submit="updateDocument" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="team-document-edit-title" class="wp-section-title">{{ __('common.button.edit') }}</h2>
                    <x-wp-modal-close wire:click="closeEditModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-document-edit-description">{{ __('team.library.description') }}</label>
                    <textarea id="team-document-edit-description" class="wp-input" rows="3" wire:model="description" maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-document-edit-file">{{ __('team.library.replace_file') }}</label>
                    <input id="team-document-edit-file" type="file" class="wp-input" wire:model="documentFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    @error('documentFile') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                @include('livewire.teams.partials.audience-fields', ['formId' => 'edit-doc', 'workers' => $workers, 'audience' => $audience])
                <label class="wp-check">
                    <input type="checkbox" wire:model="isActive">
                    <span>{{ __('team.library.active') }}</span>
                </label>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
