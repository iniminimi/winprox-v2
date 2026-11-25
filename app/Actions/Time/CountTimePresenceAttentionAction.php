<?php

namespace App\Actions\Time;

use App\Enums\WorkShiftStatus;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Time\TimePresenceAttentionRules;

class CountTimePresenceAttentionAction
{
    public function __construct(
        private ListTimeRosterViewsAction $listRosterViews,
        private CollectAbsenceClockInAttentionAction $absenceClockIns,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds  null = alle locaties
     */
    public function handle(int $tenantId, ?array $actorLocationIds = null): int
    {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->when($actorLocationIds !== null, fn ($query) => $query->whereHas(
                'presenceClockPoint',
                fn ($point) => $point->whereIn('location_id', $actorLocationIds ?: [0]),
            ))
            ->with(['openBreak', 'breaks', 'team'])
            ->get();

        $checkmateMode = (bool) Tenant::query()->whereKey($tenantId)->value('checkmate_mode');

        return TimePresenceAttentionRules::collect($openShifts, $checkmateMode)->count()
            + $this->absenceClockIns->handle($tenantId, $openShifts)->count()
            + $this->listRosterViews->handle($tenantId, actorLocationIds: $actorLocationIds)->count();
    }
}
