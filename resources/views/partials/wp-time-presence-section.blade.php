@props(['title', 'shifts', 'showForceClose' => false, 'staleHours' => 16])

<div class="wp-card wp-card-pad wp-stack">
    <h2 class="wp-section-title">{{ $title }} ({{ $shifts->count() }})</h2>
    @forelse ($shifts as $shift)
        @php
            $isStale = $shift->clock_in_at->lt(now()->subHours(max(1, (int) $staleHours)));
        @endphp
        <div class="wp-cluster wp-cluster--spread" wire:key="presence-shift-{{ $shift->id }}">
            <div class="wp-cluster">
                @if ($shift->worker?->field_icon_slug)
                    <x-wp-worker-icon :slug="$shift->worker->field_icon_slug" />
                @endif
                <div>
                    <strong>{{ $shift->worker?->displayName() }}</strong>
                    <p class="wp-muted wp-text-sm">
                        {{ $shift->team?->name }}
                        &middot; {{ $shift->clock_in_at->format('H:i') }}
                        &middot; {{ $shift->clockInClockPoint?->name }}
                    </p>
                    @if ($isStale)
                        <p class="wp-error wp-text-sm">{{ __('time.presence.stale_warning', ['hours' => $staleHours]) }}</p>
                    @endif
                </div>
            </div>
            @if ($showForceClose)
                <button type="button" class="btn btn--ghost btn--sm" wire:click="forceClose({{ $shift->id }})" wire:confirm="{{ __('time.presence.force_close_confirm') }}">
                    {{ __('time.presence.force_close') }}
                </button>
            @endif
        </div>
    @empty
        <p class="wp-muted">{{ __('time.presence.empty_section') }}</p>
    @endforelse
</div>
