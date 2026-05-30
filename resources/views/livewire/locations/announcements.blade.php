<div class="wp-card wp-card-pad wp-stack" wire:key="location-announcements-{{ $location->id }}">
    <div class="wp-row">
        <div class="wp-grow wp-stack-tight">
            <div class="wp-cluster">
                <h2 class="wp-section-title">{{ __('locations.announcements.title') }}</h2>
                <span class="wp-pill wp-pill--closed">{{ $announcements->count() }}</span>
            </div>
            <p class="wp-muted">{{ __('locations.announcements.subtitle') }}</p>
        </div>
        <button type="button" class="btn btn--primary btn--sm" wire:click="openCreateModal">
            {{ __('locations.announcements.add') }}
        </button>
    </div>

    <div class="wp-list">
        @forelse ($announcements as $announcement)
            <div class="wp-location-unit-row" wire:key="ann-{{ $announcement->id }}">
                <div class="wp-grow wp-stack-tight">
                    <p class="wp-issue-desc">{{ $announcement->body }}</p>
                    <p class="wp-muted wp-text-sm">
                        {{ __('locations.announcements.unit_label') }}:
                        {{ $announcement->unit?->name ?? __('locations.announcements.for_location') }}
                        @if ($announcement->expires_at)
                            · {{ __('locations.announcements.expires_on', ['date' => $announcement->expires_at->format('d-m-Y')]) }}
                        @endif
                        @if (! $announcement->is_active)
                            · <span class="wp-pill wp-pill--closed">{{ __('locations.inactive') }}</span>
                        @endif
                    </p>
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditModal({{ $announcement->id }})">
                        {{ __('common.button.edit') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleAnnouncementActive({{ $announcement->id }})">
                        {{ $announcement->is_active ? __('locations.announcements.deactivate') : __('locations.announcements.activate') }}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="deleteAnnouncement({{ $announcement->id }})"
                            wire:confirm="{{ __('locations.announcements.confirm_delete') }}">
                        {{ __('common.button.delete') }}
                    </button>
                </div>
            </div>
        @empty
            <p class="wp-muted">{{ __('locations.announcements.empty') }}</p>
        @endforelse
    </div>

    @if ($showCreateModal)
        <div class="wp-modal">
            <form wire:submit="createAnnouncement" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.announcements.add') }}</h2>
                    <x-wp-modal-close wire:click="closeCreateModal" />
                </div>
                <p class="wp-muted">{{ __('locations.announcements.modal_subtitle') }}</p>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.body') }}</span>
                    <textarea class="wp-input" rows="4" wire:model="body"></textarea>
                    @error('body') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.link_unit') }}</span>
                    <select class="wp-select" wire:model="unitId">
                        <option value="">{{ __('locations.announcements.for_location') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.expires_at') }}</span>
                    <input type="date" class="wp-input" wire:model="expiresAt">
                    @error('expiresAt') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.announcements.active') }}</label>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeCreateModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if ($showEditModal)
        <div class="wp-modal">
            <form wire:submit="updateAnnouncement" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.announcements.edit') }}</h2>
                    <x-wp-modal-close wire:click="closeEditModal" />
                </div>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.body') }}</span>
                    <textarea class="wp-input" rows="4" wire:model="body"></textarea>
                    @error('body') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.link_unit') }}</span>
                    <select class="wp-select" wire:model="unitId">
                        <option value="">{{ __('locations.announcements.for_location') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.expires_at') }}</span>
                    <input type="date" class="wp-input" wire:model="expiresAt">
                    @error('expiresAt') <span class="wp-error">{{ $message }}</span> @enderror
                </label>

                <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.announcements.active') }}</label>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
