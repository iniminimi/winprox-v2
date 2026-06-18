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
            <div class="wp-issue-row" wire:key="ann-{{ $announcement->id }}">
                <div class="wp-grow wp-stack-tight">
                    <p class="wp-issue-desc">{{ $announcement->description }}</p>
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

                <div class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.description') }}</span>
                    <div class="wp-stack-tight">
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea class="wp-input" rows="4" wire:model="description"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
                        @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                    </div>
                </div>

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

                <div class="wp-stack-tight">
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.announcements.active') }}</label>
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
            <form wire:submit="updateAnnouncement" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 class="wp-section-title">{{ __('locations.announcements.edit') }}</h2>
                    <x-wp-modal-close wire:click="closeEditModal" />
                </div>

                <div class="wp-field">
                    <span class="wp-label">{{ __('locations.announcements.description') }}</span>
                    <div class="wp-stack-tight">
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea class="wp-input" rows="4" wire:model="description"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
                        @error('description') <span class="wp-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if ($editingAnnouncement?->is_active)
                    <div class="wp-card wp-card-pad wp-surface-muted wp-stack-tight">
                        <span class="wp-label">{{ __('locations.announcements.translation_preview') }}</span>
                        <select
                            class="wp-select wp-select--inline"
                            wire:model.live="previewLocale"
                            aria-label="{{ __('issues.show.description_language') }}"
                        >
                            @foreach ($descriptionLocales as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p @class(['wp-text-body', 'wp-issue-description-text', 'wp-muted' => $previewDescriptionMissing])>{{ $previewDescription }}</p>
                    </div>
                @endif

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

                <div class="wp-stack-tight">
                    <label class="wp-check"><input type="checkbox" wire:model="isActive"> {{ __('locations.announcements.active') }}</label>
                </div>

                <div class="wp-row">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
    @endif
</div>
