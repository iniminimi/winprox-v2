<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                :title="__('camera.title', ['name' => $unit->localizedName()])"
                :subtitle="__('camera.subtitle')"
                help-page="cameras.show"
            />
            <p>
                <a href="{{ route('locations.show', ['location' => $unit->location_id, 'unit_id' => $unit->id]) }}" class="btn btn--ghost btn--sm">
                    ← {{ __('camera.back') }}
                </a>
            </p>
        </div>
    </div>

    @if (session('camera_flash'))
        <div class="wp-flash wp-flash--success">{{ session('camera_flash') }}</div>
    @endif

    <x-wp-settings-section :title="__('camera.forward.title')">
        <p class="wp-muted">{{ __('camera.forward.hint') }}</p>
        <button type="button" @class(['btn', 'btn--sm', 'btn--primary' => $camera->forwarding_enabled, 'btn--ghost' => ! $camera->forwarding_enabled]) wire:click="toggleForwarding">
            {{ $camera->forwarding_enabled ? __('camera.forward.on') : __('camera.forward.off') }}
        </button>
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('camera.code.title')">
        <p class="wp-muted">{{ __('camera.code.hint') }}</p>
        @php
            $shown = $pairingCode ?? ($camera->hasOpenCode() ? $camera->pairing_code : null);
        @endphp
        @if ($shown)
            <p class="wp-display-code">{{ substr($shown, 0, 4) }}-{{ substr($shown, 4) }}</p>
            @if ($camera->pairing_expires_at)
                <p class="wp-muted">{{ __('camera.code.valid_until', ['time' => $camera->pairing_expires_at->format('H:i')]) }}</p>
            @endif
        @endif
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="issueCode">
                {{ __('camera.code.issue') }}
            </button>
            @if ($camera->isLinked())
                <button type="button" class="btn btn--surface btn--sm" wire:click="$set('confirmUnlink', true)">
                    {{ __('camera.linked.unlink') }}
                </button>
            @endif
        </div>
    </x-wp-settings-section>

    @if ($camera->isLinked())
        <x-wp-settings-section :title="__('camera.linked.title')">
            <p>
                {{ __('camera.linked.device') }}:
                <strong>{{ $camera->device_hint }}</strong>
            </p>
            <p class="wp-muted">
                {{ __('camera.linked.paired_at') }}: {{ $camera->paired_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            </p>
            <p class="wp-muted">
                {{ __('camera.linked.last_seen') }}: {{ $camera->last_seen_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—' }}
            </p>
        </x-wp-settings-section>
    @endif

    @if ($pendingClaim)
        <x-wp-settings-section :title="__('camera.pending.title')">
            <p><strong>{{ $pendingClaim->device_hint }}</strong></p>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="confirmClaim({{ $pendingClaim->id }})">
                    {{ __('camera.pending.confirm') }}
                </button>
                <button type="button" class="btn btn--surface btn--sm" wire:click="denyClaim({{ $pendingClaim->id }})">
                    {{ __('camera.pending.deny') }}
                </button>
            </div>
        </x-wp-settings-section>
    @endif

    <x-wp-settings-section :title="__('camera.photos.title')">
        @if ($photos->isEmpty())
            <p class="wp-muted">{{ __('camera.photos.empty') }}</p>
        @else
            <div class="wp-photo-grid wp-photo-grid--gallery">
                @foreach ($photos as $photo)
                    <button type="button" class="wp-photo-thumb" wire:click="openPhoto({{ $photo->id }})" wire:key="camera-photo-{{ $photo->id }}">
                        <img src="{{ route('cameras.photos.show', $photo) }}" alt="" loading="lazy">
                    </button>
                @endforeach
            </div>
            <p class="wp-muted wp-text-sm">{{ __('camera.photos.older') }}</p>
            @if ($photos->hasPages())
                <nav class="wp-pagination__pages" aria-label="{{ __('camera.photos.title') }}">
                    <button type="button" class="wp-pagination__control" wire:click="previousPage" @disabled($photos->onFirstPage()) aria-label="{{ __('camera.photos.previous') }}">‹</button>
                    <button type="button" class="wp-pagination__control" wire:click="nextPage" @disabled(! $photos->hasMorePages()) aria-label="{{ __('camera.photos.next') }}">›</button>
                </nav>
            @endif
        @endif
    </x-wp-settings-section>

    @if ($confirmUnlink)
        <x-wp-modal closeMethod="closeUnlink" aria-labelledby="camera-unlink-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="camera-unlink-title" class="wp-h2">{{ __('camera.unlink_modal.title') }}</h2>
                    <x-wp-modal-close wire:click="closeUnlink" />
                </div>
                <p class="wp-muted">{{ __('camera.unlink_modal.body') }}</p>
                <div class="wp-cluster">
                    <button type="button" class="btn btn--surface" wire:click="closeUnlink">{{ __('common.button.cancel') }}</button>
                    <button type="button" class="btn btn--primary" wire:click="unlink">{{ __('camera.unlink_modal.confirm') }}</button>
                </div>
            </div>
        </x-wp-modal>
    @endif

    @if ($openPhoto)
        <x-wp-modal closeMethod="closePhoto" aria-labelledby="camera-photo-title">
            <div class="wp-card wp-card-pad wp-stack wp-modal-card">
                <div class="wp-modal-head">
                    <h2 id="camera-photo-title" class="wp-h2">{{ $openPhoto->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</h2>
                    <x-wp-modal-close wire:click="closePhoto" />
                </div>
                <img src="{{ route('cameras.photos.show', $openPhoto) }}" alt="">
            </div>
        </x-wp-modal>
    @endif
</div>
