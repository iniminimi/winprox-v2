<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\RecordUnitGpsReportData;
use App\Events\Units\UnitGpsReported;
use App\Models\Unit;
use App\Models\UnitGpsReport;

class RecordUnitGpsReportAction
{
    public function handle(
        Unit $unit,
        RecordUnitGpsReportData $data,
        int $tenantId,
        ?int $actorUserId = null,
        ?int $workerId = null,
    ): UnitGpsReport {
        $report = UnitGpsReport::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unit->id,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'reported_at' => $data->reportedAt,
            'worker_id' => $workerId,
        ]);

        event(new UnitGpsReported($report->fresh(['worker']), $actorUserId));

        return $report;
    }
}
