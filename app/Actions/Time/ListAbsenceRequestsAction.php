<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Support\Time\AbsencePhotoRetention;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Collection;

class ListAbsenceRequestsAction
{
    /**
     * @return Collection<int, AbsenceRequest>
     */
    public function handle(
        int $tenantId,
        ?AbsenceRequestStatus $status = null,
        int $limit = 100,
        bool $expiringPhotos = false,
        ?int $teamId = null,
        ?int $workerId = null,
        ?string $from = null,
        ?string $to = null,
    ): Collection {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        return AbsenceRequest::query()
            ->with(['worker', 'shiftType', 'decidedBy', 'photos'])
            ->where('tenant_id', $tenantId)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($teamId, fn ($q) => $q->whereHas(
                'worker',
                fn ($worker) => $worker->where('internal_team_id', $teamId),
            ))
            ->when($workerId, fn ($q) => $q->where('worker_id', $workerId))
            ->when($from !== null && $from !== '', fn ($q) => $q->whereDate('date_to', '>=', $from))
            ->when($to !== null && $to !== '', fn ($q) => $q->whereDate('date_from', '<=', $to))
            ->when($expiringPhotos, function ($query) {
                $query->whereHas('photos', function ($photos) {
                    $photos->where('created_at', '<=', AbsencePhotoRetention::warnBefore())
                        ->where('created_at', '>', AbsencePhotoRetention::dropBefore());
                });
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AbsenceRequestStatus::Pending->value])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
