<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\ClockPoint;
use App\Models\User;
use App\Support\Time\AbsencePhotoRetention;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Database\Eloquent\Builder;
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
        ?int $clockPointId = null,
        bool $withPhotos = false,
        ?User $viewer = null,
    ): Collection {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        return AbsenceRequest::query()
            ->with(['worker', 'shiftType', 'decidedBy', 'photos'])
            ->where('tenant_id', $tenantId)
            ->when(
                $viewer instanceof User,
                fn ($q) => $q->whereHas('worker', fn ($worker) => $worker->visibleTo($viewer)),
            )
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($teamId, fn ($q) => $q->whereHas(
                'worker',
                fn ($worker) => $worker->where('internal_team_id', $teamId),
            ))
            ->when($workerId, fn ($q) => $q->where('worker_id', $workerId))
            ->when($clockPointId, fn ($q) => $this->whereWorkerMayClockAt($q, $tenantId, $clockPointId))
            ->when($withPhotos, fn ($q) => $q->whereHas('photos'))
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

    /**
     * Zelfde regel als inklokken: een Clock Point zonder locatie geldt voor iedereen.
     * Een uitvoerder zonder locaties, of een team dat overal mag, hoort bij elk punt.
     *
     * @param  Builder<AbsenceRequest>  $query
     */
    private function whereWorkerMayClockAt($query, int $tenantId, int $clockPointId): void
    {
        $point = ClockPoint::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($clockPointId)
            ->first(['id', 'location_id']);

        if ($point === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        if ($point->location_id === null) {
            return;
        }

        $locationId = (int) $point->location_id;

        $query->whereHas('worker', function ($worker) use ($locationId) {
            $worker->where(function ($eligible) use ($locationId) {
                $eligible->whereHas('team', fn ($team) => $team->where('clocks_all_locations', true))
                    ->orWhereDoesntHave('locations')
                    ->orWhereHas('locations', fn ($locations) => $locations->where('locations.id', $locationId));
            });
        });
    }
}
