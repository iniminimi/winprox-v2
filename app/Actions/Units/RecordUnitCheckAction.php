<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\RecordUnitCheckData;
use App\Events\Units\UnitCheckRecorded;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitCheck;
use App\Models\Worker;
use App\Support\UnitCheckPhotoStorage;
use Illuminate\Http\UploadedFile;

class RecordUnitCheckAction
{
    public function __construct(
        private UnitCheckPhotoStorage $storage,
    ) {}

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
                return $existing->loadMissing(['worker', 'unit', 'location', 'team', 'photos']);
            }
        }

        $unit->loadMissing(['location', 'unitCheckList.items']);
        $description = $data->description !== null ? trim($data->description) : null;
        $photos = array_values(array_filter(
            $data->photos,
            static fn ($photo) => $photo instanceof UploadedFile,
        ));
        if (count($photos) > 4) {
            $photos = array_slice($photos, 0, 4);
        }

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
            'checklist_failed' => $this->failedChecklistItems($unit, $data->checklistItems),
            'description' => $description !== null && $description !== '' ? $description : null,
            'external_id' => $data->externalId,
        ]);

        if ($photos !== []) {
            Tenant::query()->findOrFail($tenantId)->assertCanAddPhotos(count($photos));
        }

        foreach ($photos as $photo) {
            $check->photos()->create([
                'tenant_id' => $tenantId,
                'path' => $this->storage->storePrecompressedCopy($photo),
            ]);
        }

        $check->load(['worker', 'unit', 'location', 'team', 'photos']);

        event(new UnitCheckRecorded($check, $actorUserId));

        return $check;
    }

    /**
     * Snapshot of checklist points that were not ticked (template order).
     *
     * @param  list<string>|null  $checked
     * @return list<string>|null
     */
    private function failedChecklistItems(Unit $unit, ?array $checked): ?array
    {
        if ($unit->unitCheckList === null || ! $unit->unitCheckList->is_active) {
            return null;
        }

        $required = $unit->unitCheckList->sourceItemLabels();
        if ($required === []) {
            return null;
        }

        $ticked = array_values(array_filter(
            array_map(
                static fn ($label) => is_string($label) ? trim($label) : '',
                $checked ?? [],
            ),
            static fn (string $label) => $label !== '',
        ));
        $failed = array_values(array_diff($required, $ticked));

        return $failed === [] ? null : $failed;
    }
}
