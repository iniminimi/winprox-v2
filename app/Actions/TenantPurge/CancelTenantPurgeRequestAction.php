<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

/**
 * Annuleert een openstaande purge-aanvraag (tenant-admin of superuser).
 */
final class CancelTenantPurgeRequestAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(TenantPurgeRequest $request, User $actor): TenantPurgeRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.not_open')],
            ]);
        }

        $isTenantAdmin = $request->tenant_id !== null
            && $actor->tenant_id !== null
            && (int) $actor->tenant_id === (int) $request->tenant_id
            && $actor->isAdmin();

        if (! $isTenantAdmin && ! $actor->is_superuser) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.admin_only')],
            ]);
        }

        $tenantId = $request->tenant_id;

        $request->status = TenantPurgeStatus::Cancelled;
        $request->confirmation_token_hash = null;
        $request->save();

        if ($tenantId !== null) {
            $this->audit->record(
                userId: $actor->id,
                tenantId: (int) $tenantId,
                action: 'tenant_purge.cancelled',
                modelType: TenantPurgeRequest::class,
                modelId: $request->id,
            );
        }

        return $request->fresh();
    }
}
