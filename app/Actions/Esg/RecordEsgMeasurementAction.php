<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Data\Esg\RecordEsgMeasurementData;
use App\Events\Esg\EsgMeasurementRecorded;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Esg\EsgModuleAccess;
use Illuminate\Validation\ValidationException;

class RecordEsgMeasurementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        RecordEsgMeasurementData $data,
        int $tenantId,
        ?int $workerId = null,
        ?int $actorUserId = null,
    ): EsgMeasurement {
        $tenant = Tenant::query()->find($tenantId);
        if (! EsgModuleAccess::tenantHasModule($tenant)) {
            throw ValidationException::withMessages([
                'esg_indicator_id' => [__('esg.errors.module_disabled')],
            ]);
        }

        $task = Task::query()
            ->with(['issue.unit'])
            ->where('tenant_id', $tenantId)
            ->find($data->taskId);

        if ($task === null) {
            throw ValidationException::withMessages([
                'task_id' => [__('esg.errors.measurement_task_invalid')],
            ]);
        }

        $issue = $task->issue;
        if ($issue === null || $issue->unit_id === null) {
            throw ValidationException::withMessages([
                'task_id' => [__('esg.errors.measurement_task_unit_required')],
            ]);
        }

        if ($issue->esg_indicator_id === null) {
            throw ValidationException::withMessages([
                'esg_indicator_id' => [__('esg.errors.measurement_issue_indicator_required')],
            ]);
        }

        if ((int) $issue->esg_indicator_id !== $data->esgIndicatorId) {
            throw ValidationException::withMessages([
                'esg_indicator_id' => [__('esg.errors.measurement_indicator_mismatch')],
            ]);
        }

        $indicator = EsgIndicator::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($data->esgIndicatorId);

        if ($indicator === null) {
            throw ValidationException::withMessages([
                'esg_indicator_id' => [__('esg.errors.measurement_indicator_invalid')],
            ]);
        }

        if ($data->valueForType($indicator->type) === null) {
            throw ValidationException::withMessages([
                $indicator->type->valueColumn() => [__('esg.errors.measurement_value_required')],
            ]);
        }

        if ($workerId !== null) {
            $workerExists = Worker::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($workerId)
                ->exists();

            if (! $workerExists) {
                throw ValidationException::withMessages([
                    'worker_id' => [__('esg.errors.measurement_worker_invalid')],
                ]);
            }
        }

        if ($data->correctsMeasurementId !== null) {
            $this->assertCorrectsMeasurementIsValid(
                $data->correctsMeasurementId,
                $tenantId,
                $task->id,
                $indicator->id,
            );
        }

        $unit = $issue->unit;
        $locationId = $unit?->location_id ?? $issue->location_id;

        $measurement = EsgMeasurement::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $issue->unit_id,
            'location_id' => $locationId,
            'task_id' => $task->id,
            'esg_indicator_id' => $indicator->id,
            'worker_id' => $workerId,
            ...$data->valueColumnsForInsert($indicator->type),
            'corrects_measurement_id' => $data->correctsMeasurementId,
            'recorded_at' => $data->recordedAt,
            'created_at' => now(),
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'esg_measurement.recorded',
            modelType: EsgMeasurement::class,
            modelId: (int) $measurement->id,
            payload: [
                'id' => $measurement->id,
                'task_id' => $task->id,
                'esg_indicator_id' => $indicator->id,
                'unit_id' => $issue->unit_id,
                'location_id' => $locationId,
                'worker_id' => $workerId,
                'corrects_measurement_id' => $data->correctsMeasurementId,
                'recorded_at' => $data->recordedAt->toIso8601String(),
                'type' => $indicator->type->value,
                'value' => $data->valueForType($indicator->type),
            ],
        );

        $measurement = $measurement->fresh(['indicator', 'unit', 'location', 'worker']);

        event(new EsgMeasurementRecorded($measurement, $actorUserId));

        return $measurement;
    }

    private function assertCorrectsMeasurementIsValid(
        int $correctsMeasurementId,
        int $tenantId,
        int $taskId,
        int $indicatorId,
    ): void {
        $original = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($correctsMeasurementId)
            ->first();

        if ($original === null) {
            throw ValidationException::withMessages([
                'corrects_measurement_id' => [__('esg.errors.measurement_correction_invalid')],
            ]);
        }

        if ((int) $original->task_id !== $taskId || (int) $original->esg_indicator_id !== $indicatorId) {
            throw ValidationException::withMessages([
                'corrects_measurement_id' => [__('esg.errors.measurement_correction_mismatch')],
            ]);
        }
    }
}
