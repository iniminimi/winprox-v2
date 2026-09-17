<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class UpdateTenantTimeClockSecurityAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{time_require_worker_pin?: bool, time_gps_on_clock?: bool, time_gps_visits?: bool, time_gps_visit_radius_meters?: int|null, time_evacuation_list?: bool}  $data
     */
    public function handle(Tenant $tenant, int $tenantId, array $data, ?int $actorUserId): Tenant
    {
        if ((int) $tenant->id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! TimeModuleAccess::tenantHasModule($tenant)) {
            throw new InvalidArgumentException('time_module_disabled');
        }

        $requirePin = (bool) ($data['time_require_worker_pin'] ?? false);
        $gpsOnClock = (bool) ($data['time_gps_on_clock'] ?? false);
        $gpsVisits = (bool) ($data['time_gps_visits'] ?? false);
        $evacuationList = (bool) ($data['time_evacuation_list'] ?? false);
        $radius = $data['time_gps_visit_radius_meters'] ?? null;
        $radius = $radius === null || $radius === '' ? null : (int) $radius;
        if ($radius !== null && $radius < 50) {
            $radius = 50;
        }
        if ($radius !== null && $radius > 2000) {
            $radius = 2000;
        }

        $tenant->update([
            'time_require_worker_pin' => $requirePin,
            'time_gps_on_clock' => $gpsOnClock,
            'time_gps_visits' => $gpsVisits,
            'time_gps_visit_radius_meters' => $radius,
            'time_evacuation_list' => $evacuationList,
        ]);

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.time_clock_security_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'time_require_worker_pin' => $requirePin,
                'time_gps_on_clock' => $gpsOnClock,
                'time_gps_visits' => $gpsVisits,
                'time_gps_visit_radius_meters' => $radius,
                'time_evacuation_list' => $evacuationList,
            ],
        );

        return $fresh;
    }
}
