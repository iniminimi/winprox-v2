<?php

namespace App\Actions\Time;

use App\Data\Time\TimeRosterPerson;
use App\Data\Time\TimeRosterSnapshot;
use App\Enums\WorkShiftStatus;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class ListOpenTimeRosterAction
{
    /** Open diensten per locatie, nieuwste inklokking eerst. */
    public function handle(int $tenantId): TimeRosterSnapshot
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || ! $tenant->allowsEvacuationList()) {
            throw new InvalidArgumentException('evacuation_list_disabled');
        }

        $people = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with([
                'worker.user',
                'worker.team.translations',
                'team.translations',
                'openBreak',
                'presenceClockPoint.location',
                'clockInClockPoint.location',
            ])
            ->orderByDesc('clock_in_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WorkShift $shift) => TimeRosterPerson::fromOpenShift($shift))
            ->values();

        $byLocation = $people
            ->groupBy(fn (TimeRosterPerson $person) => $person->locationName)
            ->sortKeys();

        return new TimeRosterSnapshot(
            people: $people,
            byLocation: $byLocation,
            count: $people->count(),
        );
    }
}
