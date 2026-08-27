<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\EsgMeasurement;
use App\Support\Esg\EsgMeasurementPresenter;
use Illuminate\Support\Collection;

final class EsgMeasurementExportTable
{
    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        return [
            __('reports.columns.id'),
            __('reports.columns.recorded_at'),
            __('reports.columns.indicator'),
            __('reports.columns.value'),
            __('reports.columns.location'),
            __('reports.columns.unit'),
            __('reports.columns.worker'),
            __('reports.columns.task_id'),
        ];
    }

    /**
     * @param  Collection<int, EsgMeasurement>  $measurements
     * @return Collection<int, list<string>>
     */
    public static function rows(Collection $measurements): Collection
    {
        return $measurements->map(function (EsgMeasurement $measurement): array {
            return [
                (string) $measurement->id,
                $measurement->recorded_at?->format('Y-m-d H:i') ?? '',
                (string) ($measurement->indicator?->localizedName() ?? ''),
                EsgMeasurementPresenter::displayValue($measurement),
                (string) ($measurement->location?->localizedName() ?? $measurement->location?->name ?? ''),
                (string) ($measurement->unit?->localizedName() ?? $measurement->unit?->name ?? ''),
                (string) ($measurement->worker?->displayName() ?? ''),
                $measurement->task_id !== null ? (string) $measurement->task_id : '',
            ];
        });
    }
}
