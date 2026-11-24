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

    <x-wp-settings-section :title="__('screen.code.title')">
        <p class="wp-muted">{{ __('screen.code.hint') }}</p>
        <p class="wp-muted">
            {{ __('screen.limits') }}
            <a href="{{ route('sense.index') }}">{{ __('common.nav.sense') }}</a>
        </p>

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
