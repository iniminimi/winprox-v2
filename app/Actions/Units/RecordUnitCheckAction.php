<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\RecordUnitCheckData;
use App\Events\Units\UnitCheckRecorded;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;

class RecordUnitCheckAction
{
    public function handle(
        Unit $unit,
        RecordUnitCheckData $data,
        int $tenantId,
        ?Worker $worker = null,
        ?int $actorUserId = null,
    ): UnitCheck {
        if ($data->externalId !== null) {
            $existing = UnitCheck::query()
                ->where('tenant_id', $tenantId)
                ->where('external_id', $data->externalId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $unit->loadMissing('location');

        $check = UnitCheck::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unit->id,
            'location_id' => $unit->location_id,
            'worker_id' => $worker?->id,
            'internal_team_id' => $worker?->internal_team_id,
            'result' => $data->result,
            'source' => $data->source,
            'checked_at' => $data->checkedAt,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'task_id' => $data->taskId,
            'issue_id' => $data->issueId,
            'checklist_items' => $data->checklistItems,
            'external_id' => $data->externalId,
        ]);

        event(new UnitCheckRecorded(
            $check->fresh(['worker', 'unit', 'location', 'team']),
            $actorUserId,
        ));

        return $check;
    }
}
