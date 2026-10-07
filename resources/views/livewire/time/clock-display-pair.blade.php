<div class="wp-stack" wire:poll.5s>
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('time.clock_displays.title', ['name' => $point?->name])"
                :subtitle="__('time.clock_displays.subtitle')"
                help-page="time.clock_display"
            />
            <p>
                <a href="{{ route('time.clock-points.index') }}" class="btn btn--ghost btn--sm">
                    ← {{ __('time.clock_displays.back') }}
                </a>
            </p>
        </div>
    </div>

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    {{-- Gekoppeld scherm --}}
    @if ($point?->hasLinkedDisplay())
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-cluster wp-cluster--wrap">
                <p class="wp-section-title">{{ __('time.clock_displays.linked.title') }}</p>
                <span class="wp-pill wp-pill--done">{{ __('time.clock_displays.linked.active') }}</span>
            </div>
            <p class="wp-issue-card-meta">
                {{ __('time.clock_displays.linked.device') }}: <strong>{{ $point->display_device_hint }}</strong>
            </p>
            <p class="wp-issue-card-meta">
                {{ __('time.clock_displays.linked.paired_at') }}: {{ $point->display_paired_at?->format('d/m/Y H:i') }}
                @if ($point->display_last_seen_at)
                    — {{ __('time.clock_displays.linked.last_seen') }}: {{ $point->display_last_seen_at->diffForHumans() }}
                @endif
            </p>
            <div class="wp-cluster">
                <button type="button" class="btn btn--ghost btn--sm" wire:click="$set('confirmRotate', true)">
                    {{ __('time.clock_displays.linked.rotate') }}
                </button>
                <button type="button" class="btn btn--surface btn--sm" wire:click="$set('confirmUnlink', true)">
                    {{ __('time.clock_displays.linked.unlink') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Aan-uren van het scherm --}}
    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-section-title">{{ __('time.clock_displays.schedule.title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.hint') }}</p>
        @php
            $scheduleSlots = [];
            for ($h = 0; $h < 24; $h++) {
                $scheduleSlots[] = sprintf('%02d:00', $h);
                $scheduleSlots[] = sprintf('%02d:30', $h);
            }
        @endphp
        <div class="wp-cluster wp-cluster--wrap">
            <span class="wp-label">{{ __('time.clock_displays.schedule.block') }} :</span>
            <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.from') }}</span>
            <select id="display-on-from" class="wp-input wp-input--auto" wire:model="displayOnFrom">
                <option value="">{{ __('time.clock_displays.schedule.always_on') }}</option>
                @foreach ($scheduleSlots as $slot)
                    <option value="{{ $slot }}">{{ $slot }}</option>
                @endforeach
            </select>
            <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.until') }}</span>
            <select id="display-on-until" class="wp-input wp-input--auto" wire:model="displayOnUntil">
                <option value="">{{ __('time.clock_displays.schedule.always_on') }}</option>
                @foreach ($scheduleSlots as $slot)
                    <option value="{{ $slot }}">{{ $slot }}</option>
                @endforeach
            </select>
        </div>
        @error('displayOnFrom') <p class="wp-error">{{ $message }}</p> @enderror
        @error('displayOnUntil') <p class="wp-error">{{ $message }}</p> @enderror
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="saveSchedule">
                {{ __('time.clock_displays.schedule.save') }}
            </button>
        </div>
    </div>

    {{-- Rust-scherm tijdens de album-vensters: foto's, wijzerklok of niets --}}
    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-section-title">{{ __('time.clock_displays.album.title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.hint') }}</p>

        <div class="wp-field">
            <label class="wp-label" for="album-mode">{{ __('time.clock_displays.album.mode_label') }}</label>
            <select id="album-mode" class="wp-input wp-input--auto" wire:model.live="albumMode">
                <option value="photos">{{ __('time.clock_displays.album.mode_photos') }}</option>
                <option value="clock">{{ __('time.clock_displays.album.mode_clock') }}</option>
                <option value="none">{{ __('time.clock_displays.album.mode_none') }}</option>
            </select>
            @error('albumMode') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        @if ($albumMode !== 'none')
        @foreach ([1 => 'album1', 2 => 'album2'] as $n => $p)
            <div class="wp-cluster wp-cluster--wrap">
                <span class="wp-label">{{ __('time.clock_displays.album.block') }} {{ $n }} :</span>
                <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.from') }}</span>
                <select id="{{ $p }}-from" class="wp-input wp-input--auto" wire:model="{{ $p }}From">
                    <option value="">—</option>
                    @foreach ($scheduleSlots as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
                <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.until') }}</span>
                <select id="{{ $p }}-until" class="wp-input wp-input--auto" wire:model="{{ $p }}Until">
                    <option value="">—</option>
                    @foreach ($scheduleSlots as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
            </div>
            @error($p.'From') <p class="wp-error">{{ $message }}</p> @enderror
            @error($p.'Until') <p class="wp-error">{{ $message }}</p> @enderror
        @endforeach
        <div class="wp-cluster wp-cluster--wrap">
            <span class="wp-label">{{ __('time.clock_displays.album.days_label') }}</span>
            @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $i => $dayKey)
                <label class="wp-muted wp-text-sm">
                    <input type="checkbox" wire:model="albumDays.{{ $i }}">
                    {{ __('time.clock_displays.album.days.'.$dayKey) }}
                </label>
            @endforeach
        </div>
        @endif
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="saveAlbumWindows">
                {{ __('time.clock_displays.album.save') }}
            </button>
        </div>

        @if ($albumMode === 'photos')
        <div class="wp-cluster wp-cluster--wrap">
            <input
                wire:ignore
                type="file"
                id="albumPhotoInput"
                class="wp-file-input-native"
                accept="image/jpeg,image/png,image/webp,image/*"
                aria-label="{{ __('time.clock_displays.album.upload') }}"
                x-on:change="
                    const input = $event.target;
                    const file = input.files?.[0];
                    if (!file) { return; }
                    const crop = typeof window.wpCropImageFile === 'function'
                        ? window.wpCropImageFile(file, {
                            aspectRatio: 1,
                            outputSize: 480,
                            title: @js(__('time.clock_displays.album.crop_title')),
                            applyLabel: @js(__('time.clock_displays.album.crop_apply')),
                            cancelLabel: @js(__('common.button.cancel')),
                          })
                        : Promise.resolve(file);
                    crop.then((cropped) => {
                        if (!cropped) { return null; }
                        if (typeof window.wpCompressImageFile !== 'function') { return cropped; }
                        return window.wpCompressImageFile(cropped, { maxDimension: 480, quality: 0.8 });
                    }).then((compressed) => {
                        if (!compressed) { return; }
                        $wire.upload('albumPhoto', compressed);
                    }).finally(() => { input.value = ''; });
                "
            >
            @if ($albumImages->count() >= \App\Models\ClockDisplayImage::MAX_PER_SCOPE)
                <button type="button" class="btn btn--primary btn--sm" disabled>
                    {{ __('time.clock_displays.album.max_reached') }}
                </button>
            @else
                <label for="albumPhotoInput" class="btn btn--primary btn--sm wp-file-input-trigger">
                    {{ __('time.clock_displays.album.upload') }}
                </label>
            @endif
            <label class="wp-muted wp-text-sm">
                <input type="checkbox" wire:model="albumGlobal">
                {{ __('time.clock_displays.album.all_points') }}
            </label>
        </div>
        @error('albumPhoto') <p class="wp-error">{{ $message }}</p> @enderror

        @if ($albumImages->isNotEmpty())
            <div class="wp-photo-grid">
                @foreach ($albumImages as $img)
                    <div class="wp-photo-thumb">
                        <img src="{{ $img->publicUrl() }}" alt="" loading="lazy">
                        @if ($img->clock_point_id === null)
                            <span class="wp-photo-thumb-tag">{{ __('time.clock_displays.album.all_points_badge') }}</span>
                        @endif
                        <button
                            type="button"
                            class="wp-photo-remove"
                            wire:click="deleteAlbumImage({{ $img->id }})"
                            aria-label="{{ __('common.button.delete') }}"
                        >✕</button>
                    </div>
                @endforeach
            </div>
        @endif
        @endif
    </div>

    {{-- Pairing-code --}}
    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-section-title">{{ __('time.clock_displays.code.title') }}</p>
        <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.code.hint') }}</p>
        @if ($point?->hasLinkedDisplay())
            <div class="wp-flash wp-flash--danger">{{ __('time.clock_displays.code.replace_warning') }}</div>
        @endif
        @if ($pairingCode !== null)
            <p class="wp-display-code">{{ $pairingCode }}</p>
            <p class="wp-muted wp-text-sm">
                {{ __('time.clock_displays.code.valid_until', ['time' => $point?->display_pairing_expires_at?->format('H:i')]) }}
            </p>
        @elseif ($point?->display_pairing_code !== null && $point?->display_pairing_expires_at?->isFuture())
            <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.code.open') }}</p>
        @endif
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="issueCode">
                {{ __('time.clock_displays.code.issue') }}
            </button>
        </div>
    </div>

    {{-- Pending claim --}}
    @if ($pendingClaim !== null)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-cluster wp-cluster--wrap">
                <p class="wp-section-title">{{ __('time.clock_displays.pending.title') }}</p>
                <span class="wp-pill wp-pill--progress">{{ __('time.clock_displays.pending.waiting') }}</span>
            </div>
            <p class="wp-issue-card-meta">
                {{ __('time.clock_displays.pending.device') }}: <strong>{{ $pendingClaim->device_hint }}</strong>
                @if ($pendingClaim->ip)
                    — IP {{ $pendingClaim->ip }}
                @endif
            </p>
            <p class="wp-issue-card-meta">
                {{ __('time.clock_displays.pending.expires', ['time' => $pendingClaim->expires_at?->diffForHumans()]) }}
            </p>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="confirmClaim({{ $pendingClaim->id }})">
                    {{ __('time.clock_displays.pending.confirm') }}
                </button>
                <button type="button" class="btn btn--surface btn--sm" wire:click="denyClaim({{ $pendingClaim->id }})">
                    {{ __('time.clock_displays.pending.deny') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Confirm modals --}}
    @if ($confirmUnlink)
        <x-wp-modal closeMethod="$set('confirmUnlink', false)" aria-labelledby="unlink-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="unlink-title" class="wp-h2">{{ __('time.clock_displays.unlink_modal.title') }}</h2>
                    <x-wp-modal-close wire:click="$set('confirmUnlink', false)" />
                </div>
                <p class="wp-muted">{{ __('time.clock_displays.unlink_modal.body') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="$set('confirmUnlink', false)">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="unlink">{{ __('time.clock_displays.unlink_modal.confirm') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($confirmRotate)
        <x-wp-modal closeMethod="$set('confirmRotate', false)" aria-labelledby="rotate-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="rotate-title" class="wp-h2">{{ __('time.clock_displays.rotate_modal.title') }}</h2>
                    <x-wp-modal-close wire:click="$set('confirmRotate', false)" />
                </div>
                <p class="wp-muted">{{ __('time.clock_displays.rotate_modal.body') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="$set('confirmRotate', false)">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="rotateSecret">{{ __('time.clock_displays.rotate_modal.confirm') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
