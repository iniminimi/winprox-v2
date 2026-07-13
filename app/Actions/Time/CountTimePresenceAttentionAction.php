<?php

namespace App\Actions\Time;

use App\Enums\WorkShiftStatus;
use App\Models\WorkShift;
use App\Support\Time\TimePresenceAttentionRules;

class CountTimePresenceAttentionAction
{
    public function handle(int $tenantId): int
    {
        $openShifts = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->with(['openBreak', 'breaks'])
            ->get();

        return TimePresenceAttentionRules::collect($openShifts)->count();
    }
}
