<?php

declare(strict_types=1);

namespace App\Http\Controllers\Esg;

use App\Actions\Esg\ExportEsgMeasurementsAction;
use App\Data\Esg\ExportEsgMeasurementsFilterData;
use App\Http\Requests\Esg\ExportEsgMeasurementsRequest;
use App\Models\EsgMeasurement;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\EsgMeasurementExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EsgMeasurementExportController
{
    public function __invoke(ExportEsgMeasurementsRequest $request, ExportEsgMeasurementsAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', EsgMeasurement::class);

        $result = $export->handle((int) Tenancy::id(), $this->filters($request));
        $rows = EsgMeasurementExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.esg.filename').'-'.now()->format('Y-m-d').'.csv',
            EsgMeasurementExportTable::columns(),
            $rows,
        );
    }

    private function filters(ExportEsgMeasurementsRequest $request): ExportEsgMeasurementsFilterData
    {
        return new ExportEsgMeasurementsFilterData(
            indicatorId: $request->integer('indicator') ?: null,
            locationId: $request->integer('location') ?: null,
            unitId: $request->integer('unit') ?: null,
            recordedFrom: (string) ($request->validated('from') ?? ''),
            recordedTo: (string) ($request->validated('to') ?? ''),
            alarmsOnly: $request->boolean('alarms'),
        );
    }
}
