<div class="wp-stack" data-manual-capture="time-clock-points">
    <x-wp-page-head-title
        :title="__('time.title')"
        help-page="time.clock_points"
        :subtitle="__('time.clock_points.subtitle')"
    />

    @include('partials.wp-time-nav')

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
            <div class="wp-card wp-card-pad wp-cluster wp-cluster--spread" wire:key="clock-point-{{ $clockPoint->id }}">
                <div>
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
        <x-wp-modal closeMethod="closeModal">
            <h2 class="wp-section-title">
                {{ $editingClockPointId ? __('time.clock_points.edit_title') : __('time.clock_points.create_title') }}
            </h2>
            <form wire:submit="save" class="wp-stack">
                <div class="wp-field">
                    <label class="wp-label" for="cp-name">{{ __('time.clock_points.fields.name') }}</label>
                    <input id="cp-name" type="text" class="wp-input" wire:model="name">
                    @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <div class="wp-field">
                    <label class="wp-label" for="cp-location">{{ __('time.clock_points.fields.location') }}</label>
                    <select id="cp-location" class="wp-input" wire:model="locationId">
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
                    <button type="button" class="btn btn--ghost" wire:click="closeModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
    @endif
</div>
