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
     * @param  array{time_require_worker_pin?: bool, time_gps_on_clock?: bool}  $data
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

        $tenant->update([
            'time_require_worker_pin' => $requirePin,
            'time_gps_on_clock' => $gpsOnClock,
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
            ],
        );

        return $fresh;
    }
}
