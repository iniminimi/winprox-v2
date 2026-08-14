<div class="wp-stack" data-manual-capture="time-clock-points">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('time.clock_points.title')"
                help-page="time.clock_points"
                :subtitle="__('time.clock_points.subtitle')"
            />
        </div>
        @can('create', \App\Models\ClockPoint::class)
            <button type="button" class="btn btn--primary" wire:click="openCreate">{{ __('time.clock_points.add') }}</button>
        @endcan
    </div>

    @include('partials.wp-time-nav', ['alarmCount' => $alarmCount])

    @if (session('time_flash'))
        <div class="wp-flash wp-flash--success">{{ session('time_flash') }}</div>
    @endif

    @if ($blockedQrAttempts > 0)
        <div class="wp-flash wp-flash--danger">{{ __('time.clock_points.qr.blocked_attempts', ['count' => $blockedQrAttempts]) }}</div>
    @endif

    @can('create', \App\Models\ClockPoint::class)
        <div class="wp-card wp-card-pad wp-time-qr-rotation" x-data="{ open: false }">
            <form wire:submit="saveQrRotationSettings" class="wp-stack-tight">
                <button
                    type="button"
                    class="wp-settings-section-toggle"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-controls="qr-rotation-fields"
                    title="{{ __('time.clock_points.qr.rotation_hint') }}"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                    <span class="wp-section-title">{{ __('time.clock_points.qr.rotation_title') }}</span>
                </button>
                <div id="qr-rotation-fields" class="wp-filter-cell" x-show="open" x-cloak>
                    <label class="wp-filter-inline-label" for="qr-rotation-months">{{ __('time.clock_points.qr.rotation_months') }}</label>
                    <input id="qr-rotation-months" type="number" min="0" max="120" class="wp-input" wire:model="qrRotationMonths">
                    <button type="submit" class="btn btn--surface btn--sm">{{ __('common.button.save') }}</button>
                    @error('qrRotationMonths') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
            </form>
        </div>
    @endcan

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-list wp-list--entity-rows">
            @forelse ($clockPoints as $clockPoint)
                <div class="wp-issue-row" wire:key="clock-point-{{ $clockPoint->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $clockPoint->name }}</p>
                        @if ($clockPoint->location)
                            <p class="wp-issue-card-meta">{{ $clockPoint->location->localizedName() }}</p>
                        @endif
                    </div>
                    <div class="wp-cluster wp-cluster--wrap">
                        <span class="wp-pill {{ $clockPoint->is_active ? 'wp-pill--done' : 'wp-pill--closed' }}">
                            {{ $clockPoint->is_active ? __('time.clock_points.status.active') : __('time.clock_points.status.inactive') }}
                        </span>
                        @if ($clockPoint->isRenewalRecommended())
                            <span class="wp-pill wp-pill--progress">{{ __('time.clock_points.qr.renewal_recommended') }}</span>
                        @endif
                        @can('view', $clockPoint)
                            <button type="button" class="btn btn--surface btn--sm" wire:click="openQrPackModal({{ $clockPoint->id }})">
                                {{ __('common.qr.button') }}
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
                <p class="wp-muted">{{ __('time.clock_points.empty') }}</p>
            @endforelse
        </div>
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
        @php
            $clockQrRenewMethod = null;
        @endphp
        @can('renewQr', $qrPackClockPoint)
            @php
                $clockQrRenewMethod = 'renewQr('.$qrPackClockPoint->id.')';
            @endphp
        @endcan
        <x-wp-qr-cluster-modal
            closeMethod="closeQrPackModal"
            title-id="clock-point-qr-cluster-modal-title"
            :title="__('common.qr.modal_title')"
            :subtitle="$qrPackClockPoint->name"
            :print-url="route('time.clock-points.qr', $qrPackClockPoint)"
            :print-label="__('common.qr.print')"
            :formats="collect($qrPackTemplates)->map(fn ($template) => [
                'key' => $qrPackClockPoint->id.'-'.$template->value,
                'title' => __('common.qr.formats.'.$template->value.'.title'),
                'size' => __('common.qr.formats.'.$template->value.'.size'),
                'url' => route('time.clock-points.qr-pack', [
                    'clockPoint' => $qrPackClockPoint,
                    'template' => $template->value,
                ]),
            ])->all()"
            :generating="__('time.clock_points.qr.pack.generating')"
            :download-failed="__('time.clock_points.qr.pack.download_failed')"
            :renew-method="$clockQrRenewMethod"
            :renew-label="__('time.clock_points.qr.renew')"
            :renew-confirm="__('time.clock_points.qr.renew_confirm')"
        />
    @endif
</div>
