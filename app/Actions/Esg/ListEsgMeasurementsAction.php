<?php

declare(strict_types=1);

namespace App\Actions\Esg;

use App\Models\EsgMeasurement;
use App\Support\Esg\EsgMeasurementThresholdQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListEsgMeasurementsAction
{
    /**
     * @return LengthAwarePaginator<int, EsgMeasurement>
     */
    public function handle(
        int $tenantId,
        ?int $indicatorId = null,
        ?int $locationId = null,
        ?int $unitId = null,
        ?string $recordedFrom = null,
        ?string $recordedTo = null,
        bool $alarmsOnly = false,
        int $perPage = 25,
    ): LengthAwarePaginator {
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
                'thresholdFollowUpTask:id,status,esg_threshold_measurement_id',
                'correctsMeasurement.indicator:id,name,original_language,type,unit_of_measure,thresholds,options',
                'correctsMeasurement.indicator.translations',
            ])
            ->when($indicatorId !== null, fn ($query) => $query->where('esg_indicator_id', $indicatorId))
            ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->when(filled($recordedFrom), fn ($query) => $query->whereDate('recorded_at', '>=', $recordedFrom))
            ->when(filled($recordedTo), fn ($query) => $query->whereDate('recorded_at', '<=', $recordedTo));

        if ($alarmsOnly) {
            EsgMeasurementThresholdQuery::applyOutsideThresholds($query);
        }

        return $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
