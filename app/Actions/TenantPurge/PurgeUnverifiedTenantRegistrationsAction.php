<?php

namespace App\Actions\TenantPurge;

use App\Enums\UnusedTenantDeletionReason;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Wist zelfregistraties waarvan het e-mailadres nooit geverifieerd is. Zo blijft een valse
 * of verkeerd getypte aanmelding niet weken in productie staan.
 */
final class PurgeUnverifiedTenantRegistrationsAction
{
    public function __construct(private DeleteUnusedTenantAction $deleteUnused) {}

    /**
     * @return array{scanned: int, deleted: int}
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $days = max(1, (int) config('tenant_purge.unverified_registration_days', 7));
        $cutoff = $now->copy()->subDays($days);

        $stats = ['scanned' => 0, 'deleted' => 0];

        Tenant::query()
            ->whereNull('billing_plan')
            ->where('created_at', '<=', $cutoff)
            ->whereHas('users')
            ->whereDoesntHave('users', function ($query): void {
                $query->whereNotNull('email_verified_at');
            })
            ->orderBy('id')
            ->each(function (Tenant $tenant) use (&$stats): void {
                $stats['scanned']++;

                $this->deleteUnused->handle(
                    tenant: $tenant,
                    actor: null,
                    reason: UnusedTenantDeletionReason::UnverifiedRegistration,
                );

                $stats['deleted']++;
            });

        return $stats;
    }
}
