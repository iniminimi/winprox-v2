{{ __('mail.tenant_purge.confirm.greeting', ['name' => $adminName]) }}

{{ __('mail.tenant_purge.confirm.intro', ['tenant' => $tenantName]) }}

@if ($track === 'paid')
{{ __('mail.tenant_purge.confirm.paid_note') }}
@else
{{ __('mail.tenant_purge.confirm.trial_note') }}
@endif

{{ __('mail.tenant_purge.confirm.cta') }}
{{ $confirmUrl }}

{{ __('mail.tenant_purge.confirm.expiry', ['hours' => $hours]) }}

{{ __('mail.tenant_purge.confirm.footer') }}
