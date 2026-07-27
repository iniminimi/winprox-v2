{{ __('mail.tenant_purge.scheduled.greeting', ['name' => $adminName]) }}

{{ __('mail.tenant_purge.scheduled.body', [
    'tenant' => $tenantName,
    'date' => $scheduledAt,
    'timezone' => $timezone,
]) }}

{{ __('mail.tenant_purge.scheduled.cancel') }}
{{ $subscriptionUrl }}

{{ __('mail.tenant_purge.scheduled.footer') }}
