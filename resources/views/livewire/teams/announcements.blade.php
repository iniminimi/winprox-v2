<div wire:key="team-announcements-{{ $team->id }}" id="team-announcements-{{ $team->id }}">
    <x-wp-disclosure-card
        entangle="panelOpen"
        :title="__('team.library.announcements_title')"
        :subtitle="__('team.library.announcements_subtitle')"
        :count="$announcements->total()"
    >
        <x-slot:toolbar>
            <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
                {{ __('team.library.add_announcement') }}
            </button>
        </x-slot:toolbar>

        <div class="wp-filter-cell">
            <span class="wp-filter-inline-label">{{ __('team.workers_index.filters.label') }}</span>
            <input
                type="search"
                class="wp-input wp-input--compact"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('team.library.filters.search_announcements') }}"
                aria-label="{{ __('team.library.filters.search_announcements') }}"
            >
            <select class="wp-select wp-select--compact" wire:model.live="audienceFilter" aria-label="{{ __('team.library.audience') }}">
                <option value="all">{{ __('team.library.filters.audience_all') }}</option>
                <option value="team">{{ __('team.library.audience_team') }}</option>
                @if ($workers->isNotEmpty())
                    <optgroup label="{{ __('team.library.worker') }}">
                        @foreach ($workers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <select class="wp-select wp-select--compact" wire:model.live="statusFilter" aria-label="{{ __('team.workers_index.filters.status') }}">
                    <option value="active">{{ __('team.library.filters.status_active') }}</option>
                    <option value="expired">{{ __('team.library.filters.status_expired') }}</option>
                    <option value="inactive">{{ __('team.library.filters.status_inactive') }}</option>
                    <option value="all">{{ __('team.library.filters.status_all') }}</option>
            </select>
        </div>

        <div class="wp-list">
            @forelse ($announcements as $announcement)
                <div class="wp-issue-row" wire:key="team-announcement-{{ $announcement->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-desc">{{ $announcement->description }}</p>
                        <p class="wp-muted wp-text-sm">
                            {{ $announcement->worker?->displayName() ?? __('team.library.audience_team') }}
                            @if ($announcement->expires_at)
                                · {{ __('team.library.expires_on', ['date' => $announcement->expires_at->format('d-m-Y')]) }}
                            @endif
                            @unless ($announcement->is_active)
                                · <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                            @elseif ($announcement->isExpired())
                                · <span class="wp-pill wp-pill--closed">{{ __('team.library.filters.status_expired') }}</span>
                            @endunless
                        </p>
                    </div>
                    <div class="wp-cluster">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $announcement->id }})">
                            {{ __('common.button.edit') }}
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteAnnouncement({{ $announcement->id }})"
                                wire:confirm="{{ __('team.library.confirm_delete_announcement') }}">
                            {{ __('common.button.delete') }}
                        </button>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ $hasAnnouncements ? __('team.library.filters.empty') : __('team.library.empty_announcements') }}</p>
            @endforelse
        </div>

        @if ($announcements->hasPages())
            <div class="wp-pagination">
                {{ $announcements->links() }}
            </div>
        @endif
    </x-wp-disclosure-card>

    @if ($showCreateModal)
        <x-wp-modal closeMethod="closeCreateModal" aria-labelledby="team-announcement-create-title">
            <form wire:submit="createAnnouncement" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="team-announcement-create-title" class="wp-section-title">{{ __('team.library.add_announcement') }}</h2>
                    <x-wp-modal-close wire:click="closeCreateModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-announcement-description">{{ __('team.library.description') }}</label>
                    <textarea id="team-announcement-description" class="wp-input" rows="3" wire:model="description" maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-announcement-expires">{{ __('team.library.expires') }}</label>
                    <input id="team-announcement-expires" type="date" class="wp-input" wire:model="expiresAt">
                    @error('expiresAt') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                @include('livewire.teams.partials.audience-fields', ['formId' => 'create-ann', 'workers' => $workers, 'audience' => $audience])
                <div>
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('team.library.active') }}</label>
                    <p class="wp-hint">{{ __('team.library.announcement_active_hint') }}</p>
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeCreateModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showEditModal)
        <x-wp-modal closeMethod="closeEditModal" aria-labelledby="team-announcement-edit-title">
            <form wire:submit="updateAnnouncement" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="team-announcement-edit-title" class="wp-section-title">{{ __('common.button.edit') }}</h2>
                    <x-wp-modal-close wire:click="closeEditModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-announcement-edit-description">{{ __('team.library.description') }}</label>
                    <textarea id="team-announcement-edit-description" class="wp-input" rows="3" wire:model="description" maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"></textarea>
                    @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="team-announcement-edit-expires">{{ __('team.library.expires') }}</label>
                    <input id="team-announcement-edit-expires" type="date" class="wp-input" wire:model="expiresAt">
                </div>
                @include('livewire.teams.partials.audience-fields', ['formId' => 'edit-ann', 'workers' => $workers, 'audience' => $audience])
                <div>
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('team.library.active') }}</label>
                    <p class="wp-hint">{{ __('team.library.announcement_active_hint') }}</p>
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
