@props([
    'worker',
])

<div class="wp-time-presence-row wp-time-presence-row--absent" wire:key="presence-absent-{{ $worker->id }}">
    <div class="wp-time-presence-row__identity">
        <x-wp-worker-avatar
            :worker="$worker"
            size="sm"
            tone="absent"
            class="wp-time-presence-row__initial wp-time-presence-row__initial--absent"
        />
        <div class="wp-time-presence-row__copy">
            <span class="wp-time-presence-row__name">{{ $worker->displayName() }}</span>
        </div>
    </div>
    <span class="wp-muted wp-text-sm wp-time-presence-row__status">{{ __('time.presence.not_clocked_in') }}</span>
</div>
