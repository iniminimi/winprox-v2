<?php

namespace App\Actions\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Auth\LoginAlertWindow;

/**
 * Noteert een geslaagde beheerlogin en de alarmen die daaruit volgen.
 */
class RecordBackofficeLoginAction
{
    private const BURST_MINUTES = 15;

    private const BURST_COUNT = 3;

    public function __construct(private AuditRecorder $audit) {}

    public function handle(User $user): void
    {
        if ($user->tenant_id === null) {
            return;
        }

        $tenantId = (int) $user->tenant_id;
        $this->audit->record(
            userId: (int) $user->id,
            tenantId: $tenantId,
            action: 'auth.login',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['user_name' => $user->name],
        );

        $user->loadMissing('tenant');
        $tenant = $user->tenant;
        if ($tenant !== null && $tenant->login_alert_enabled) {
            $now = now();
            if (LoginAlertWindow::contains($now, (string) $tenant->login_alert_start, (string) $tenant->login_alert_end)) {
                $this->audit->record(
                    userId: (int) $user->id,
                    tenantId: $tenantId,
                    action: 'auth.login_outside_hours',
                    modelType: User::class,
                    modelId: (int) $user->id,
                    payload: ['user_name' => $user->name],
                );
            }
        }

        $since = now()->subMinutes(self::BURST_MINUTES);
        $logins = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('model_id', $user->id)
            ->where('action', 'auth.login')
            ->where('created_at', '>=', $since)
            ->count();

        $burstExists = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->where('model_id', $user->id)
            ->where('action', 'auth.session_burst')
            ->where('created_at', '>=', $since)
            ->exists();

        if ($logins >= self::BURST_COUNT && ! $burstExists) {
            $this->audit->record(
                userId: (int) $user->id,
                tenantId: $tenantId,
                action: 'auth.session_burst',
                modelType: User::class,
                modelId: (int) $user->id,
                payload: [
                    'user_name' => $user->name,
                    'count' => $logins,
                ],
            );
        }
    }
}
