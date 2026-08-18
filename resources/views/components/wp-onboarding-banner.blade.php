@props([
    'stage',
])

@php
    use App\Support\Onboarding\TenantOnboardingState;
    use App\Support\Time\TimeModuleAccess;

    $href = match ($stage) {
        'teams' => route('team.index', ['section' => 'teams']),
        'categories' => route('locations.index', ['section' => 'categories']),
        'locations' => route('locations.index', ['section' => 'locations']),
        'units' => TenantOnboardingState::unitsOnboardingHref(),
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
        <div class="wp-cluster wp-cluster--tight">
            {{ $slot }}
            <a href="{{ $href }}"
               class="btn btn--sm {{ $slot->isEmpty() ? 'btn--primary wp-badge-critical' : 'btn--ghost' }}">
                {{ __('dashboard.onboarding.'.$stage.'.button') }}
            </a>
        </div>
    </div>
</div>
