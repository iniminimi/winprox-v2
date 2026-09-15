<?php

namespace App\Actions\Time;

use App\Events\Time\ShiftTypeSaved;
use App\Models\ShiftType;
use App\Support\Time\TimeModuleAccess;

class SetShiftTypeActiveAction
{
    public function handle(ShiftType $shiftType, bool $isActive, ?int $actorUserId): ShiftType
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $shiftType->tenant_id);

        $shiftType->update(['is_active' => $isActive]);
        $fresh = $shiftType->fresh();
        event(new ShiftTypeSaved($fresh, $actorUserId));

        return $fresh;
    }
}
