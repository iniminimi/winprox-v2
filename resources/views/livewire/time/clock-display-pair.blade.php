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
        <div class="wp-measure-field-range">
            <div class="wp-field">
                <label class="wp-label" for="display-on-from">{{ __('time.clock_displays.schedule.from') }}</label>
                <select id="display-on-from" class="wp-input" wire:model="displayOnFrom">
                    <option value="">{{ __('time.clock_displays.schedule.always_on') }}</option>
                    @foreach ($scheduleSlots as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
                @error('displayOnFrom') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-field">
                <label class="wp-label" for="display-on-until">{{ __('time.clock_displays.schedule.until') }}</label>
                <select id="display-on-until" class="wp-input" wire:model="displayOnUntil">
                    <option value="">{{ __('time.clock_displays.schedule.always_on') }}</option>
                    @foreach ($scheduleSlots as $slot)
                        <option value="{{ $slot }}">{{ $slot }}</option>
                    @endforeach
                </select>
                @error('displayOnUntil') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary btn--sm" wire:click="saveSchedule">
                {{ __('time.clock_displays.schedule.save') }}
            </button>
        </div>
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
