<?php

namespace App\Actions\TenantPurge;

use App\Actions\Audit\LogAuditAction;
use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Enums\UnusedTenantDeletionReason;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Wist een nooit-gebruikt account (niet-geverifieerde registratie of spam) volledig.
 * Loopt via de bestaande purge-pijplijn, dus met SQL-snapshot als terugvaloptie.
 */
final class DeleteUnusedTenantAction
{
    public function __construct(
        private ExecuteTenantPurgeAction $executePurge,
        private LogAuditAction $logAudit,
    ) {}

    /**
     * @param  User|null  $actor  superuser die wist; null = systeem (onderhoudstaak)
     * @param  string|null  $confirmName  door de superuser getypte organisatienaam
     */
    public function handle(
        Tenant $tenant,
        ?User $actor,
        UnusedTenantDeletionReason $reason,
        ?string $confirmName = null,
    ): void
    {
        if ($actor !== null && trim((string) $confirmName) !== trim((string) $tenant->name)) {
            throw ValidationException::withMessages([
                'deleteConfirmName' => [__('platform.tenants_delete.errors.name_mismatch')],
            ]);
        }

        $tenantId = (int) $tenant->id;
        $tenantName = (string) $tenant->name;

        $request = TenantPurgeRequest::query()->create([
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'track' => TenantPurgeTrack::Unused,
            'status' => TenantPurgeStatus::Scheduled,
            'initiated_by_user_id' => $actor?->id,
            'scheduled_purge_at' => now(),
        ]);

        $this->executePurge->handle($request, $actor);

        // Platform-niveau (tenant_id null): blijft bestaan nadat de tenant-audit gewist is.
        $this->logAudit->handle(
            userId: $actor?->id,
            tenantId: null,
            action: 'tenant_purge.unused_deleted',
            modelType: Tenant::class,
            modelId: $tenantId,
            payload: [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'reason' => $reason->value,
                'by_superuser' => $actor !== null,
            ],
        );
    }
}
