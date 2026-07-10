<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;
use Carbon\Carbon;

class ScheduleClockPointQrRecommendationAction
{
    public function handle(ClockPoint $clockPoint, Tenant $tenant, ?Carbon $renewedAt = null): ClockPoint
    {
        $months = $tenant->effectiveTimeQrRotationMonths();
        $renewedAt ??= $clockPoint->qr_renewed_at ?? now();

        if ($months === null) {
            $clockPoint->update(['qr_renewal_recommended_at' => null]);

            return $clockPoint->fresh();
        }

        $intervalDays = max(1, $months * 30);
        $randomOffsetDays = random_int(1, $intervalDays);

        $clockPoint->update([
            'qr_renewal_recommended_at' => $renewedAt->copy()->addDays($randomOffsetDays),
        ]);

        return $clockPoint->fresh();
    }
}
