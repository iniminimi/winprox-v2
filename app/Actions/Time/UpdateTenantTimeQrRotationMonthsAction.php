<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

class UpdateTenantTimeQrRotationMonthsAction
{
    public function __construct(
        private ScheduleClockPointQrRecommendationAction $scheduleRecommendation,
        private AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, ?int $months, ?int $actorUserId): Tenant
    {
        if ($months !== null && ($months < 0 || $months > 120)) {
            throw new InvalidArgumentException('invalid_rotation_months');
        }

        $tenant->update(['time_qr_rotation_months' => $months]);

        ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->each(function (ClockPoint $clockPoint) use ($tenant): void {
                $this->scheduleRecommendation->handle(
                    $clockPoint,
                    $tenant,
                    $clockPoint->qr_renewed_at ?? now(),
                );
            });

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.time_qr_rotation_updated',
            modelType: Tenant::class,
            modelId: $tenant->id,
            payload: ['time_qr_rotation_months' => $months],
        );

        return $tenant->fresh();
    }
}
