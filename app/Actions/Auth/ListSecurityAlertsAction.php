<?php

namespace App\Actions\Auth;

use App\Models\AuditLog;

/**
 * Recente aanmeldalarmen voor de kaart op het beginscherm.
 *
 * @return list<array{action: string, at: \Carbon\CarbonInterface, user_name: string, device: string, revoked: string, count: int}>
 */
class ListSecurityAlertsAction
{
    public function handle(int $tenantId): array
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('action', [
                'auth.login_outside_hours',
                'auth.login_throttled',
                'auth.phone_registered',
                'auth.session_burst',
            ])
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (AuditLog $log): array {
                $payload = $log->payload ?? [];

                return [
                    'action' => $log->action,
                    'at' => $log->created_at ?? now(),
                    'user_name' => (string) ($payload['user_name'] ?? ''),
                    'device' => (string) ($payload['device_label'] ?? ''),
                    'revoked' => (string) ($payload['revoked_device_label'] ?? ''),
                    'count' => (int) ($payload['count'] ?? 0),
                ];
            })
            ->all();
    }
}
