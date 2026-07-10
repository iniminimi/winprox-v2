@props([
    'stage',
])

@php
    use App\Support\Time\TimeModuleAccess;

    $href = match ($stage) {
        'teams' => route('team.index'),
        'categories' => route('locations.index'),
        'clock_point' => TimeModuleAccess::activeTenantHasModule()
            ? route('time.clock-points.index')
            : route('team.index'),
        default => route('dashboard'),
    };

    $onboardingKey = $stage === 'clock_point' && ! TimeModuleAccess::activeTenantHasModule()
        ? 'clock_point_facility'
        : $stage;
@endphp

<div class="wp-card wp-card-pad wp-onboarding-card">
    <div class="wp-stack">
        <p class="wp-text-body"><strong>{{ __('dashboard.onboarding.'.$onboardingKey.'.title') }}</strong></p>
        <p class="wp-muted">{{ __('dashboard.onboarding.'.$onboardingKey.'.text') }}</p>
        <a href="{{ $href }}"
           class="btn btn--primary btn--sm wp-badge-critical">
            {{ __('dashboard.onboarding.'.$onboardingKey.'.button') }}
        </a>
    </div>
</div>
