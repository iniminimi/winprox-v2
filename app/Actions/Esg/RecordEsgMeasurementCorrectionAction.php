<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Data\Esg\RecordEsgMeasurementData;
use App\Http\Requests\Esg\RecordEsgMeasurementCorrectionRequest;
use App\Models\EsgMeasurement;
use App\Support\Audit\AuditRecorder;

class RecordEsgMeasurementCorrectionAction
{
    public function __construct(
        private RecordEsgMeasurementAction $record,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        EsgMeasurement $original,
        RecordEsgMeasurementData $data,
        int $tenantId,
        ?int $actorUserId = null,
    ): EsgMeasurement {
        RecordEsgMeasurementCorrectionRequest::assertOriginalCanBeCorrected($original);

        if ((int) $data->correctsMeasurementId !== (int) $original->id) {
            $data = new RecordEsgMeasurementData(
                taskId: $data->taskId,
                esgIndicatorId: $data->esgIndicatorId,
                recordedAt: $data->recordedAt,
                valueNumeric: $data->valueNumeric,
                valueBoolean: $data->valueBoolean,
                valueString: $data->valueString,
                valueJson: $data->valueJson,
                correctsMeasurementId: $original->id,
            );
        }

        $measurement = $this->record->handle(
            $data,
            $tenantId,
            $original->worker_id,
            $actorUserId,
        );

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'esg_measurement.corrected',
            modelType: EsgMeasurement::class,
            modelId: (int) $measurement->id,
            payload: [
                'id' => $measurement->id,
                'corrects_measurement_id' => $original->id,
                'esg_indicator_id' => $original->esg_indicator_id,
                'task_id' => $original->task_id,
            ],
        );

        return $measurement;
    }
}
