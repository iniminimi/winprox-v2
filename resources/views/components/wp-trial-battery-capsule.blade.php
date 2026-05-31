@props([
    'state' => null,
])

@php
    $battery = is_array($state) ? $state : null;
@endphp

@if ($battery)
    @php
        $kind = (string) ($battery['type'] ?? 'trial');
        $blocksRemaining = (int) ($battery['blocks_remaining'] ?? 0);
        $daysRemaining = (int) ($battery['days_remaining'] ?? 0);
        $imageLevel = max(1, min(5, 6 - $blocksRemaining));
        $shortText = $kind === 'grace'
            ? __('dashboard.trial_capsule.grace_short', ['days' => $daysRemaining])
            : __('dashboard.trial_capsule.trial_short', ['days' => $daysRemaining]);
        $ariaTitle = $kind === 'grace'
            ? __('dashboard.trial_capsule.grace_title')
            : __('dashboard.trial_capsule.trial_title');
    @endphp

    <a
        href="{{ route('subscription.index') }}"
        class="wp-dashboard-trial-capsule"
        aria-label="{{ $ariaTitle }}: {{ $shortText }}"
    >
        <span class="wp-dashboard-trial-capsule__pulse" aria-hidden="true"></span>
        <span class="wp-dashboard-trial-capsule__shine" aria-hidden="true"></span>
        <span class="wp-dashboard-trial-capsule__body">
            <x-wp-trial-battery-icon :level="$imageLevel" class="wp-dashboard-trial-capsule__icon" />
            <span class="wp-dashboard-trial-capsule__text">{{ $shortText }}</span>
        </span>
    </a>
@endif
