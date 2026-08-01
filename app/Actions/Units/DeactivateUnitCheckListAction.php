<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\UnitCheckList;
use App\Support\Audit\AuditRecorder;

class DeactivateUnitCheckListAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(UnitCheckList $list, ?int $actorUserId = null): UnitCheckList
    {
        $list->update(['is_active' => false]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $list->tenant_id,
            action: 'unit_check_list.deactivated',
            modelType: UnitCheckList::class,
            modelId: (int) $list->id,
            payload: ['id' => $list->id, 'name' => $list->name],
        );

        return $list->fresh();
    }
}
