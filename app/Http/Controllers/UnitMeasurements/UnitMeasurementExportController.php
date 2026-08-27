<?php

declare(strict_types=1);

namespace App\Http\Controllers\UnitMeasurements;

use App\Actions\UnitMeasurements\ExportUnitMeasurementsAction;
use App\Data\UnitMeasurements\ExportUnitMeasurementsFilterData;
use App\Http\Requests\UnitMeasurements\ExportUnitMeasurementsRequest;
use App\Models\UnitMeasurement;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\UnitMeasurementExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitMeasurementExportController
{
    public function __invoke(ExportUnitMeasurementsRequest $request, ExportUnitMeasurementsAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', UnitMeasurement::class);

        $result = $export->handle((int) Tenancy::id(), $this->filters($request));
        $rows = UnitMeasurementExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.unit_measurements.filename').'-'.now()->format('Y-m-d').'.csv',
            UnitMeasurementExportTable::columns(),
            $rows,
        );
    }

    private function filters(ExportUnitMeasurementsRequest $request): ExportUnitMeasurementsFilterData
    {
        return new ExportUnitMeasurementsFilterData(
            locationId: $request->integer('location') ?: null,
            fieldId: $request->integer('field') ?: null,
            search: (string) ($request->validated('q') ?? ''),
        );
    }
}
