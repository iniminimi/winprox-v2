@props([
    'stage',
])

@php
    use App\Support\Time\TimeModuleAccess;

    $href = match ($stage) {
        'teams' => route('team.index', ['section' => 'teams']),
        'categories' => route('locations.index', ['section' => 'categories']),
        'clock_point' => TimeModuleAccess::activeTenantHasModule()
            ? route('time.clock-points.index')
            : route('team.index', ['section' => 'teams']),
        default => route('dashboard'),
    };
@endphp

<div class="wp-card wp-card-pad wp-onboarding-card">
    <div class="wp-stack">
        <p class="wp-text-body"><strong>{{ __('dashboard.onboarding.'.$stage.'.title') }}</strong></p>
        <p class="wp-muted">{{ __('dashboard.onboarding.'.$stage.'.text') }}</p>
        <a href="{{ $href }}"
           class="btn btn--primary btn--sm wp-badge-critical">
            {{ __('dashboard.onboarding.'.$stage.'.button') }}
        </a>
    </div>
</div>
