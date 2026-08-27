<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Data\Reports\ListExportResult;
use App\Data\UnitMeasurements\ExportUnitMeasurementsFilterData;
use App\Models\UnitMeasurement;
use App\Support\Reports\ListExportLimit;

class ExportUnitMeasurementsAction
{
    /**
     * @return ListExportResult<UnitMeasurement>
     */
    public function handle(int $tenantId, ExportUnitMeasurementsFilterData $filters): ListExportResult
    {
        $limit = ListExportLimit::MAX;

        $query = UnitMeasurement::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit', 'location', 'field', 'worker'])
            ->when($filters->locationId, fn ($q) => $q->where('location_id', $filters->locationId))
            ->when($filters->fieldId, fn ($q) => $q->where('unit_measure_field_id', $filters->fieldId))
            ->when(trim($filters->search) !== '', function ($q) use ($filters) {
                $like = '%'.trim($filters->search).'%';
                $q->where(function ($builder) use ($like): void {
                    $builder->where('value_string', 'like', $like)
                        ->orWhereHas('field', fn ($field) => $field->where('name', 'like', $like))
                        ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', $like))
                        ->orWhereHas('location', function ($location) use ($like): void {
                            $location->where('name', 'like', $like)->orWhere('address', 'like', $like);
                        });
                });
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        $rows = $query->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit)->values();
        }

        return new ListExportResult($rows, $truncated, $limit);
    }
}
