<?php

namespace App\Actions\Retention;

use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Zichtbare zipbestanden op Abonnement. Alleen wat nog binnen de termijn valt. */
final class ListTenantTimeArchivesAction
{
    /**
     * @return Collection<int, TenantTimeArchive>
     */
    public function handle(Tenant $tenant, ?CarbonInterface $now = null): Collection
    {
        $rows = TenantTimeArchive::query()
            ->where('tenant_id', $tenant->id)
            ->where('available_until', '>', $now ?? now())
            ->get();

        return $rows
            ->where('kind', TenantTimeArchive::KIND_YEAR)
            ->sortByDesc('period_key')
            ->values()
            ->concat(
                $rows->where('kind', TenantTimeArchive::KIND_PHOTOS)->sortByDesc('period_key')->values(),
            )
            ->values();
    }
}
