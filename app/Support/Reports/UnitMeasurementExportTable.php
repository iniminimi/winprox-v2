<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\UnitMeasurement;
use Illuminate\Support\Collection;

final class UnitMeasurementExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.recorded_at'),
            __('reports.columns.field'),
            __('reports.columns.value'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.worker'),
            __('reports.columns.source'),
        ];
    }

    /**
     * @param  Collection<int, UnitMeasurement>  $measurements
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $measurements): Collection
    {
        return $measurements->map(function (UnitMeasurement $measurement): array {
            return [
                (string) $measurement->id,
                $measurement->recorded_at?->format('Y-m-d H:i') ?? '',
                (string) ($measurement->field?->name ?? ''),
                $measurement->displayValue(),
                (string) ($measurement->location?->name ?? ''),
                (string) ($measurement->unit?->name ?? ''),
                (string) ($measurement->worker?->displayName() ?? ''),
                $measurement->source
                    ? __('unit_measurements.sources.'.$measurement->source->value)
                    : '',
            ];
        });
    }
}
