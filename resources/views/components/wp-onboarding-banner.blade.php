@props([
    'stage',
])

<div class="wp-card wp-card-pad wp-onboarding-card">
    <div class="wp-stack">
        <p class="wp-text-body"><strong>{{ __('dashboard.onboarding.'.$stage.'.title') }}</strong></p>
        <p class="wp-muted">{{ __('dashboard.onboarding.'.$stage.'.text') }}</p>
        <a href="{{ $stage === 'teams' ? route('team.index') : route('locations.index') }}"
           class="btn btn--primary btn--sm wp-badge-critical">
            {{ __('dashboard.onboarding.'.$stage.'.button') }}
        </a>
    </div>
</div>
