@php
    $prev = app()->getLocale();
    app()->setLocale($locale ?? $prev);
@endphp
{{ __('mail.tenant_purge.completed.greeting', ['name' => $adminName]) }}

{{ __('mail.tenant_purge.completed.intro', ['tenant' => $tenantName]) }}

@foreach ($counts as $key => $count)
- {{ __('mail.tenant_purge.completed.count.'.$key, ['count' => $count]) }}
@endforeach

{{ __('mail.tenant_purge.completed.backup', ['date' => $backupExpiresAt]) }}

{{ __('mail.tenant_purge.completed.media') }}

{{ __('mail.tenant_purge.completed.footer') }}
@php app()->setLocale($prev); @endphp
