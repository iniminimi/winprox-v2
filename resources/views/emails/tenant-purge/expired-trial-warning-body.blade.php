<p>{{ __('mail.tenant_purge.expired_trial_warning.body', [
    'tenant' => $tenantName,
    'date' => $scheduledAt,
    'timezone' => $timezone,
]) }}</p>

<p>{{ __('mail.tenant_purge.expired_trial_warning.export') }}</p>

<p style="text-align: center; margin-top: 24px;">
    <a href="{{ $subscriptionUrl }}" style="display: inline-block; background-color: #059669; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px;">
        {{ __('mail.tenant_purge.expired_trial_warning.cta') }}
    </a>
</p>

<p style="font-size: 13px; color: #64748b; text-align: center; margin-top: 16px;">
    {{ __('mail.tenant_purge.link_fallback') }}<br>
    <a href="{{ $subscriptionUrl }}" style="color: #059669; word-break: break-all;">{{ $subscriptionUrl }}</a>
</p>

<p>{{ __('mail.tenant_purge.expired_trial_warning.cancel') }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.tenant_purge.expired_trial_warning.footer', ['days' => $retentionDays]) }}</p>
