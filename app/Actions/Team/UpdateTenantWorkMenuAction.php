<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class UpdateTenantWorkMenuAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{
     *     work_menu_calendar_enabled: bool,
     *     work_menu_reservations_enabled: bool,
     *     work_menu_inspection_rounds_enabled: bool,
     *     work_menu_unit_measurements_enabled: bool,
     * }  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Tenant
    {
        $tenant->update([
            'work_menu_calendar_enabled' => (bool) $data['work_menu_calendar_enabled'],
            'work_menu_reservations_enabled' => (bool) $data['work_menu_reservations_enabled'],
            'work_menu_inspection_rounds_enabled' => (bool) $data['work_menu_inspection_rounds_enabled'],
            'work_menu_unit_measurements_enabled' => (bool) $data['work_menu_unit_measurements_enabled'],
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.work_menu_updated',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'work_menu_calendar_enabled' => (bool) $tenant->work_menu_calendar_enabled,
                'work_menu_reservations_enabled' => (bool) $tenant->work_menu_reservations_enabled,
                'work_menu_inspection_rounds_enabled' => (bool) $tenant->work_menu_inspection_rounds_enabled,
                'work_menu_unit_measurements_enabled' => (bool) $tenant->work_menu_unit_measurements_enabled,
            ],
        );

        return $tenant->fresh();
    }
}
