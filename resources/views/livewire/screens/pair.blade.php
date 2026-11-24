<div class="wp-stack" wire:poll.5s>
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('screen.title', ['name' => $unit->localizedName()])"
                :subtitle="__('screen.subtitle')"
                help-page="units.screen"
            />
            <p>
                <a href="{{ route('locations.show', $unit->location_id) }}" class="btn btn--ghost btn--sm">
                    ← {{ __('screen.back') }}
                </a>
            </p>
        </div>
    </div>

    @if (session('screen_flash'))
        <div class="wp-flash wp-flash--success">{{ session('screen_flash') }}</div>
    @endif

    <x-wp-settings-section :title="__('time.clock_displays.schedule.title')">
        <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.hint') }}</p>
        @php
            $scheduleSlots = [];
            for ($h = 0; $h < 24; $h++) {
                $scheduleSlots[] = sprintf('%02d:00', $h);
                $scheduleSlots[] = sprintf('%02d:30', $h);
            }
            $albumSlots = $scheduleSlots;
            if (filled($displayOnFrom) && filled($displayOnUntil)) {
                $overnight = $displayOnFrom > $displayOnUntil;
                $albumSlots = array_values(array_filter(
                    $scheduleSlots,
                    fn (string $s) => $overnight
                        ? ($s >= $displayOnFrom || $s <= $displayOnUntil)
                        : ($s >= $displayOnFrom && $s <= $displayOnUntil),
                ));
            }
        @endphp
        <div class="wp-cluster wp-cluster--wrap">
            <span class="wp-label">{{ __('time.clock_displays.schedule.block') }} :</span>
            <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.from') }}</span>
            <select id="screen-on-from" class="wp-input wp-input--auto" wire:model.live="displayOnFrom">
                <option value="">{{ __('time.clock_displays.schedule.always_on') }}</option>
                @foreach ($scheduleSlots as $slot)
                    <option value="{{ $slot }}">{{ $slot }}</option>
                @endforeach
            </select>
            <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.schedule.until') }}</span>
            <select id="screen-on-until" class="wp-input wp-input--auto" wire:model.live="displayOnUntil">
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
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('time.clock_displays.sound.title')">
        <label class="wp-muted">
            <input type="checkbox" wire:model.live="displaySoundEnabled">
            {{ __('time.clock_displays.sound.label') }}
            <br><span class="wp-hint">{{ __('time.clock_displays.sound.hint') }}</span>
        </label>
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('time.clock_displays.album.title')">
        <p class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.hint') }}</p>
        <div class="wp-cluster wp-cluster--wrap">
            <label class="wp-label" for="screen-album-mode">{{ __('time.clock_displays.album.mode_label') }}</label>
            <select id="screen-album-mode" class="wp-input wp-input--auto" wire:model.live="albumMode">
                <option value="none">{{ __('time.clock_displays.album.mode_none') }}</option>
                <option value="photos">{{ __('time.clock_displays.album.mode_photos') }}</option>
                <option value="clock">{{ __('time.clock_displays.album.mode_clock') }}</option>
                <option value="flipclock">{{ __('time.clock_displays.album.mode_flipclock') }}</option>
                <option value="black">{{ __('time.clock_displays.album.mode_black') }}</option>
            </select>
        </div>
        @if ($albumMode !== 'none')
            @foreach ([1 => 'album1', 2 => 'album2'] as $n => $p)
                <div class="wp-cluster wp-cluster--wrap">
                    <span class="wp-label">{{ __('time.clock_displays.album.block') }} {{ $n }} :</span>
                    <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.from') }}</span>
                    <select id="screen-{{ $p }}-from" class="wp-input wp-input--auto" wire:model="{{ $p }}From">
                        <option value="">—</option>
                        @foreach ($albumSlots as $slot)
                            <option value="{{ $slot }}">{{ $slot }}</option>
                        @endforeach
                    </select>
                    <span class="wp-muted wp-text-sm">{{ __('time.clock_displays.album.until') }}</span>
                    <select id="screen-{{ $p }}-until" class="wp-input wp-input--auto" wire:model="{{ $p }}Until">
                        <option value="">—</option>
                        @foreach ($albumSlots as $slot)
                            <option value="{{ $slot }}">{{ $slot }}</option>
                        @endforeach
                    </select>
                </div>
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
                    id="screenAlbumPhoto"
                    class="wp-file-input-native"
                    accept="image/jpeg,image/png,image/webp,image/*"
                    aria-label="{{ __('time.clock_displays.album.upload') }}"
                    x-on:change="
                        const input = $event.target;
                        const file = input.files?.[0];
                        if (!file) { return; }
                        const crop = typeof window.wpCropImageFile === 'function'
                            ? window.wpCropImageFile(file, {
                                free: true,
                                maxEdge: 480,
                                title: @js(__('common.photo.crop_title')),
                                applyLabel: @js(__('common.photo.crop_apply')),
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
                    <label for="screenAlbumPhoto" class="btn btn--primary btn--sm wp-file-input-trigger">
                        {{ __('time.clock_displays.album.upload') }}
                    </label>
                @endif
            </div>
            @if ($albumImages->isNotEmpty())
                <div class="wp-photo-grid">
                    @foreach ($albumImages as $img)
                        <div class="wp-photo-thumb">
                            <img src="{{ $img->publicUrl() }}" alt="" loading="lazy">
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
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('screen.qr.title')">
        <p class="wp-muted">{{ __('screen.qr.hint') }}</p>
        <p class="wp-muted">
            {{ __('screen.limits') }}
            <a href="{{ route('sense.index') }}">{{ __('common.nav.sense') }}</a>
        </p>
        @if ($qrSvg)
            <div class="wp-cluster">{!! $qrSvg !!}</div>
        @endif
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('screen.code.title')">
        <p class="wp-muted">{{ __('screen.code.hint') }}</p>
        @php
            $shown = $pairingCode ?? ($screen?->hasOpenCode() ? $screen->pairing_code : null);
        @endphp
        @if ($shown)
            <p class="wp-display-code">{{ substr($shown, 0, 4) }}-{{ substr($shown, 4) }}</p>
            @if ($screen?->pairing_expires_at)
                <p class="wp-muted">{{ __('screen.code.valid_until', ['time' => $screen->pairing_expires_at->format('H:i')]) }}</p>
            @endif
        @endif
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="issueCode">
                {{ __('screen.code.issue') }}
            </button>
            @if ($screen?->isLinked())
                <button type="button" class="btn btn--surface btn--sm" wire:click="$set('confirmUnlink', true)">
                    {{ __('screen.linked.unlink') }}
                </button>
            @endif
        </div>
    </x-wp-settings-section>

    @if ($screen?->isLinked())
        <x-wp-settings-section :title="__('screen.linked.title')">
            <p>
                {{ __('screen.linked.device') }}:
                <strong>{{ $screen->device_hint }}</strong>
            </p>
            <p class="wp-muted">
                {{ __('screen.linked.paired_at') }}: {{ $screen->paired_at?->format('d/m/Y H:i') }}
            </p>
        </x-wp-settings-section>
    @endif

    @if ($pendingClaim)
        <x-wp-settings-section :title="__('screen.pending.title')">
            <p><strong>{{ $pendingClaim->device_hint }}</strong></p>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="confirmClaim({{ $pendingClaim->id }})">
                    {{ __('screen.pending.confirm') }}
                </button>
                <button type="button" class="btn btn--surface btn--sm" wire:click="denyClaim({{ $pendingClaim->id }})">
                    {{ __('screen.pending.deny') }}
                </button>
            </div>
        </x-wp-settings-section>
    @endif

    @if ($confirmUnlink)
        <x-wp-modal closeMethod="$set('confirmUnlink', false)" aria-labelledby="screen-unlink-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="screen-unlink-title" class="wp-h2">{{ __('screen.unlink_modal.title') }}</h2>
                    <x-wp-modal-close wire:click="$set('confirmUnlink', false)" />
                </div>
                <p class="wp-muted">{{ __('screen.unlink_modal.body') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="$set('confirmUnlink', false)">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="unlink">{{ __('screen.unlink_modal.confirm') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
