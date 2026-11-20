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

    public function handle(int $tenantId): int
    {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with(['openBreak', 'breaks', 'team'])
            ->get();

        $checkmateMode = (bool) Tenant::query()->whereKey($tenantId)->value('checkmate_mode');

        return TimePresenceAttentionRules::collect($openShifts, $checkmateMode)->count()
            + $this->absenceClockIns->handle($tenantId, $openShifts)->count()
            + $this->listRosterViews->handle($tenantId)->count();
    }
}
