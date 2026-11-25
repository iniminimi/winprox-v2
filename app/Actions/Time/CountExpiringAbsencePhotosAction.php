<?php

namespace App\Actions\Time;

use App\Models\AbsenceRequestPhoto;
use App\Support\Time\AbsencePhotoRetention;
use App\Support\Time\TimeModuleAccess;

/**
 * Foto's die binnen een maand gewist worden (ouder dan 5 maanden, jonger dan 6).
 */
class CountExpiringAbsencePhotosAction
{
    /**
     * @param  list<int>|null  $actorLocationIds  null = alle locaties
     */
    public function handle(int $tenantId, ?array $actorLocationIds = null): int
    {
        if (! TimeModuleAccess::rosterEnabledForTenantId($tenantId)) {
            return 0;
        }

        return AbsenceRequestPhoto::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '<=', AbsencePhotoRetention::warnBefore())
            ->where('created_at', '>', AbsencePhotoRetention::dropBefore())
            ->when($actorLocationIds !== null, fn ($query) => $query->whereHas(
                'absenceRequest.worker.locations',
                fn ($locations) => $locations->whereIn('locations.id', $actorLocationIds ?: [0]),
            ))
            ->count();
    }
}
