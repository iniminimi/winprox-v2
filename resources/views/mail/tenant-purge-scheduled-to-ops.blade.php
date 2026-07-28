{{ __('mail.tenant_purge.ops_scheduled.body', [
    'tenant' => $tenantName,
    'tenant_id' => $tenantId ?? '—',
    'track' => $track,
    'date' => $scheduledAt,
    'timezone' => $timezone,
    'purge_request_id' => $purgeRequestId,
]) }}
