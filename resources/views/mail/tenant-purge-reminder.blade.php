{{ __('mail.tenant_purge.reminder.greeting', ['name' => $adminName]) }}

{{ __('mail.tenant_purge.reminder.body', [
    'tenant' => $tenantName,
    'date' => $scheduledAt,
    'timezone' => $timezone,
]) }}

{{ __('mail.tenant_purge.reminder.cancel') }}
{{ $subscriptionUrl }}

{{ __('mail.tenant_purge.reminder.footer') }}
