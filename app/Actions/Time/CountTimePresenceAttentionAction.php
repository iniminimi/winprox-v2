<?php

namespace App\Actions\Time;

use App\Enums\WorkShiftStatus;
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
        return $this->snapshot($tenantId)['attention'];
    }

    /**
     * @return array{attention: int, absence_clock_in: int}
     */
    public function snapshot(int $tenantId): array
    {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with(['openBreak', 'breaks'])
            ->get();

        $absence = $this->absenceClockIns->handle($tenantId, $openShifts);

        return [
            'attention' => TimePresenceAttentionRules::collect($openShifts)->count()
                + $absence->count()
                + $this->listRosterViews->handle($tenantId)->count(),
            'absence_clock_in' => $absence->count(),
        ];
    }
}
