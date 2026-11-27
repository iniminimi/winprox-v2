@if ($showPhoneLoginPrompt)
    <section class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('dashboard.security.prompt_title') }}</h2>
        <p class="wp-text-body">{{ __('dashboard.security.prompt_body') }}</p>
        <div class="wp-cluster">
            <button type="button" class="btn btn--primary" wire:click="decidePhoneLogin(true)">{{ __('dashboard.security.prompt_yes') }}</button>
            <button type="button" class="btn btn--ghost" wire:click="decidePhoneLogin(false)">{{ __('dashboard.security.prompt_no') }}</button>
        </div>
    </section>
@endif

@if ($securityAlerts !== [])
    <x-wp-disclosure-card
        :title="__('dashboard.security.alerts_heading', ['tenant' => $securityAlertTenantName])"
        :count="count($securityAlerts)"
        :subtitle="trans_choice('dashboard.security.alerts_count', count($securityAlerts), ['count' => count($securityAlerts)])"
    >
        @foreach ($securityAlerts as $alert)
            <div class="wp-cluster wp-cluster--tight">
                <span class="wp-pill wp-pill--progress">{{ __('dashboard.security.alerts_title') }}</span>
                <span class="wp-muted">
                    @if ($alert['action'] === 'auth.login_outside_hours')
                        {{ __('dashboard.security.outside_hours', ['name' => $alert['user_name'], 'time' => $alert['at']->timezone('Europe/Brussels')->format('d-m-Y H:i')]) }}
                    @elseif ($alert['action'] === 'auth.login_throttled')
                        {{ __('dashboard.security.throttled', ['name' => $alert['user_name'], 'time' => $alert['at']->timezone('Europe/Brussels')->format('d-m-Y H:i')]) }}
                    @elseif ($alert['action'] === 'auth.phone_registered')
                        @if ($alert['revoked'] !== '')
                            {{ __('dashboard.security.phone_registered_revoked', ['name' => $alert['user_name'], 'device' => $alert['device'], 'revoked' => $alert['revoked'], 'time' => $alert['at']->timezone('Europe/Brussels')->format('d-m-Y H:i')]) }}
                        @else
                            {{ __('dashboard.security.phone_registered', ['name' => $alert['user_name'], 'device' => $alert['device'], 'time' => $alert['at']->timezone('Europe/Brussels')->format('d-m-Y H:i')]) }}
                        @endif
                    @elseif ($alert['action'] === 'auth.session_burst')
                        {{ __('dashboard.security.session_burst', ['name' => $alert['user_name'], 'count' => $alert['count'], 'time' => $alert['at']->timezone('Europe/Brussels')->format('d-m-Y H:i')]) }}
                    @endif
                </span>
            </div>
        @endforeach
    </x-wp-disclosure-card>
@endif
