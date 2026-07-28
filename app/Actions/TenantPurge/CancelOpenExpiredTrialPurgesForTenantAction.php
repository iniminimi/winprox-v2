<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;

/**
 * Annuleert openstaande auto-purge na abonnementsactivatie (geen wachtwoord nodig).
 */
final class CancelOpenExpiredTrialPurgesForTenantAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?User $actor = null): int
    {
        $cancelled = 0;

        TenantPurgeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('track', TenantPurgeTrack::ExpiredTrial)
            ->whereIn('status', [
                TenantPurgeStatus::AwaitingEmail->value,
                TenantPurgeStatus::Ready->value,
                TenantPurgeStatus::Scheduled->value,
            ])
            ->orderBy('id')
            ->each(function (TenantPurgeRequest $request) use ($tenant, $actor, &$cancelled): void {
                $request->status = TenantPurgeStatus::Cancelled;
                $request->confirmation_token_hash = null;
                $request->save();

                $this->audit->record(
                    userId: $actor?->id,
                    tenantId: (int) $tenant->id,
                    action: 'tenant_purge.expired_trial_cancelled',
                    modelType: TenantPurgeRequest::class,
                    modelId: $request->id,
                    payload: ['reason' => 'subscription_activated'],
                );

                $cancelled++;
            });

        return $cancelled;
    }
}
