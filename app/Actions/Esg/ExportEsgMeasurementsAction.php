<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Data\Esg\ExportEsgMeasurementsFilterData;
use App\Data\Reports\ListExportResult;
use App\Models\EsgMeasurement;
use App\Support\Esg\EsgMeasurementThresholdQuery;
use App\Support\Reports\ListExportLimit;

class ExportEsgMeasurementsAction
{
    /**
     * @return ListExportResult<EsgMeasurement>
     */
    public function handle(int $tenantId, ExportEsgMeasurementsFilterData $filters): ListExportResult
    {
        $limit = ListExportLimit::MAX;

        $query = EsgMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'indicator:id,name,original_language,type,unit_of_measure,thresholds,options',
                'indicator.translations',
                'unit:id,name,original_language,location_id',
                'unit.translations',
                'location:id,name,original_language',
                'location.translations',
                'worker:id,first_name,last_name',
                'task:id,status',
                'correctsMeasurement.indicator:id,name,original_language,type,unit_of_measure,thresholds,options',
                'correctsMeasurement.indicator.translations',
            ])
            ->when($filters->indicatorId !== null, fn ($q) => $q->where('esg_indicator_id', $filters->indicatorId))
            ->when($filters->locationId !== null, fn ($q) => $q->where('location_id', $filters->locationId))
            ->when($filters->unitId !== null, fn ($q) => $q->where('unit_id', $filters->unitId))
            ->when(filled($filters->recordedFrom), fn ($q) => $q->whereDate('recorded_at', '>=', $filters->recordedFrom))
            ->when(filled($filters->recordedTo), fn ($q) => $q->whereDate('recorded_at', '<=', $filters->recordedTo));

        if ($filters->alarmsOnly) {
            EsgMeasurementThresholdQuery::applyOutsideThresholds($query);
        }

        $rows = $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
