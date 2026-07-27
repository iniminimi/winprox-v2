<p>{{ __('mail.tenant_purge.completed.intro', ['tenant' => $tenantName]) }}</p>

<ul>
@foreach ($counts as $key => $count)
    @if (! str_starts_with((string) $key, '_'))
        <li>{{ __('mail.tenant_purge.completed.count.'.$key, ['count' => $count]) }}</li>
    @endif
@endforeach
</ul>

<p>{{ __('mail.tenant_purge.completed.backup', ['date' => $backupExpiresAt]) }}</p>
<p>{{ __('mail.tenant_purge.completed.media') }}</p>
<p style="font-size: 13px; color: #64748b;">{{ __('mail.tenant_purge.completed.footer') }}</p>
