<div class="wp-stack" data-manual-capture="time-clock-points">
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.clock_points"
        :subtitle="__('time.clock_points.subtitle')"
    />

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    @if ($blockedQrAttempts > 0)
        <div class="wp-flash wp-flash--danger">{{ __('time.clock_points.qr.blocked_attempts', ['count' => $blockedQrAttempts]) }}</div>
    @endif

    @can('create', \App\Models\ClockPoint::class)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-section-title">{{ __('time.clock_points.qr.rotation_title') }}</p>
            <p class="wp-muted wp-text-sm">{{ __('time.clock_points.qr.rotation_hint') }}</p>
            <form wire:submit="saveQrRotationSettings" class="wp-cluster wp-cluster--wrap">
                <div class="wp-field">
                    <label class="wp-label" for="qr-rotation-months">{{ __('time.clock_points.qr.rotation_months') }}</label>
                    <input id="qr-rotation-months" type="number" min="0" max="120" class="wp-input" wire:model="qrRotationMonths">
                    @error('qrRotationMonths') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--surface btn--sm">{{ __('common.button.save') }}</button>
            </form>
        </div>
    @endcan

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-cluster wp-cluster--between">
            <p class="wp-section-title">{{ __('time.clock_points.list_title') }}</p>
            @can('create', \App\Models\ClockPoint::class)
                <button type="button" class="btn btn--primary btn--sm" wire:click="openCreate">{{ __('time.clock_points.add') }}</button>
            @endcan
        </div>
    </div>

    <div class="wp-list">
        @forelse ($clockPoints as $clockPoint)
            <div class="wp-card wp-card-pad wp-cluster" wire:key="clock-point-{{ $clockPoint->id }}">
                <div class="wp-grow">
                    <strong>{{ $clockPoint->name }}</strong>
                    @if ($clockPoint->location)
                        <p class="wp-muted wp-text-sm">{{ $clockPoint->location->localizedName() }}</p>
                    @endif
                    <p class="wp-muted wp-text-sm">
                        <span class="wp-pill {{ $clockPoint->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                            {{ $clockPoint->is_active ? __('time.clock_points.status.active') : __('time.clock_points.status.inactive') }}
                        </span>
                        @if ($clockPoint->isRenewalRecommended())
                            <span class="wp-pill wp-pill--progress">{{ __('time.clock_points.qr.renewal_recommended') }}</span>
                        @endif
                    </p>
                </div>
                <div class="wp-cluster">
                    <a href="{{ route('time.clock-points.qr', $clockPoint) }}" target="_blank" rel="noopener noreferrer" class="btn btn--surface btn--sm">
                        {{ __('time.clock_points.qr.button') }}
                    </a>
                    @can('view', $clockPoint)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openQrPackModal({{ $clockPoint->id }})">
                            {{ __('time.clock_points.qr.pack.button') }}
                        </button>
                    @endcan
                    @can('renewQr', $clockPoint)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="renewQr({{ $clockPoint->id }})" wire:confirm="{{ __('time.clock_points.qr.renew_confirm') }}">
                            {{ __('time.clock_points.qr.renew') }}
                        </button>
                    @endcan
                    @can('update', $clockPoint)
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEdit({{ $clockPoint->id }})">{{ __('common.button.edit') }}</button>
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleActive({{ $clockPoint->id }})">
                            {{ $clockPoint->is_active ? __('time.clock_points.deactivate') : __('time.clock_points.activate') }}
                        </button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="wp-card wp-card-pad"><p class="wp-muted">{{ __('time.clock_points.empty') }}</p></div>
        @endforelse
    </div>

    @if ($showModal)
        <x-wp-modal closeMethod="closeModal" aria-labelledby="clock-point-modal-title">
            <form wire:submit="save" class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="clock-point-modal-title" class="wp-h2">
                        {{ $editingClockPointId ? __('time.clock_points.edit_title') : __('time.clock_points.create_title') }}
                    </h2>
                    <x-wp-modal-close wire:click="closeModal" />
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="cp-name">{{ __('time.clock_points.fields.name') }}</label>
                    <input id="cp-name" type="text" class="wp-input" wire:model="name">
                    @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="cp-location">{{ __('time.clock_points.fields.location') }}</label>
                    <select id="cp-location" class="wp-select" wire:model="locationId">
                        <option value="">{{ __('time.clock_points.fields.no_location') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->localizedName() }}</option>
                        @endforeach
                    </select>
                    @error('locationId') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="cp-sort">{{ __('time.clock_points.fields.sort_order') }}</label>
                    <input id="cp-sort" type="number" min="0" class="wp-input" wire:model="sortOrder">
                    @error('sortOrder') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif

    @if ($showQrPackModal && $qrPackClockPoint)
        <x-wp-modal closeMethod="closeQrPackModal" aria-labelledby="clock-point-qr-pack-modal-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="clock-point-qr-pack-modal-title" class="wp-section-title">{{ __('time.clock_points.qr.pack.modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeQrPackModal" />
                </div>

                <p class="wp-muted">{{ __('time.clock_points.qr.pack.modal_subtitle', ['name' => $qrPackClockPoint->name]) }}</p>

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
                                this.error = exception?.message || @js(__('time.clock_points.qr.pack.download_failed'));
                            } finally {
                                this.downloading = null;
                            }
                        },
                    }"
                >
                    @foreach ($qrPackTemplates as $template)
                        @php
                            $qrPackDownloadUrl = route('time.clock-points.qr-pack', [
                                'clockPoint' => $qrPackClockPoint,
                                'template' => $template->value,
                            ]);
                        @endphp
                        <button
                            type="button"
                            class="wp-issue-row"
                            wire:key="clock-qr-pack-format-{{ $qrPackClockPoint->id }}-{{ $template->value }}"
                            @click="download(@js($qrPackDownloadUrl), @js($template->value))"
                            :disabled="downloading !== null"
                            :aria-busy="downloading === @js($template->value)"
                        >
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ __('time.clock_points.qr.pack.formats.'.$template->value.'.title') }}</p>
                                <p class="wp-muted" x-show="downloading !== @js($template->value)">{{ __('time.clock_points.qr.pack.formats.'.$template->value.'.description') }}</p>
                                <p class="wp-muted wp-cluster" x-show="downloading === @js($template->value)" x-cloak>
                                    <x-wp-spinner size="sm" :visible="true" />
                                    <span>{{ __('time.clock_points.qr.pack.generating') }}</span>
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
