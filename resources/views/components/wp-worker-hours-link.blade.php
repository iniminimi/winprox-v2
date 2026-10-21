@props(['worker'])

@php
    $canOpen = $worker instanceof \App\Models\Worker
        && auth()->user()?->can('viewAny', \App\Models\WorkShift::class);
    $hoursUrl = $canOpen
        ? route('time.shifts.index', [
            'worker' => $worker->id,
            'from' => now()->subWeeks(3)->toDateString(),
            'to' => now()->toDateString(),
        ])
        : null;
@endphp

@if ($hoursUrl)
    <a
        href="{{ $hoursUrl }}"
        title="{{ __('time.presence.open_worker_hours') }}"
        {{ $attributes->merge(['class' => 'wp-worker-hours-link']) }}
    >
        {{ $slot }}
    </a>
@else
    <div {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
