<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\SaveUnitCheckListData;
use App\Models\InternalTeam;
use App\Models\UnitCheckList;
use App\Models\UnitCheckListItem;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveUnitCheckListAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        SaveUnitCheckListData $data,
        int $tenantId,
        ?UnitCheckList $list = null,
        ?int $actorUserId = null,
    ): UnitCheckList {
        if ($data->itemLabels === []) {
            throw ValidationException::withMessages([
                'items' => [__('unit_checks.lists.errors.items_required')],
            ]);
        }

        if ($data->internalTeamId !== null) {
            $teamOk = InternalTeam::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($data->internalTeamId)
                ->exists();

            if (! $teamOk) {
                throw ValidationException::withMessages([
                    'internal_team_id' => [__('unit_checks.lists.errors.invalid_team')],
                ]);
            }
        }

        return DB::transaction(function () use ($data, $tenantId, $list, $actorUserId) {
            $payload = [
                'name' => $data->name,
                'is_active' => $data->isActive,
                'internal_team_id' => $data->internalTeamId,
            ];

            if ($list === null) {
                $list = UnitCheckList::query()->create([
                    'tenant_id' => $tenantId,
                    ...$payload,
                ]);
                $action = 'unit_check_list.created';
            } else {
                $list->update($payload);
                $action = 'unit_check_list.updated';
            }

            UnitCheckListItem::query()
                ->where('unit_check_list_id', $list->id)
                ->delete();

            foreach ($data->itemLabels as $index => $label) {
                UnitCheckListItem::query()->create([
                    'unit_check_list_id' => $list->id,
                    'label' => $label,
                    'sort_order' => $index,
                ]);
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: $action,
                modelType: UnitCheckList::class,
                modelId: (int) $list->id,
                payload: [
                    'id' => $list->id,
                    'name' => $list->name,
                    'internal_team_id' => $list->internal_team_id,
                    'item_count' => count($data->itemLabels),
                    'is_active' => $list->is_active,
                ],
            );

            return $list->fresh(['items', 'internalTeam']);
        });
    }
}
