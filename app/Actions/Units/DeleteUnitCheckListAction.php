<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\UnitCheckList;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class DeleteUnitCheckListAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(UnitCheckList $list, ?int $actorUserId = null): void
    {
        if ($list->units()->exists()) {
            throw ValidationException::withMessages([
                'list' => [__('unit_checks.lists.errors.in_use')],
            ]);
        }

        $tenantId = (int) $list->tenant_id;
        $listId = (int) $list->id;
        $name = (string) $list->name;

        $list->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit_check_list.deleted',
            modelType: UnitCheckList::class,
            modelId: $listId,
            payload: ['id' => $listId, 'name' => $name],
        );
    }
}
