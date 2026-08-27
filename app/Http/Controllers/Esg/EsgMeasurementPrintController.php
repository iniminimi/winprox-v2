<?php

declare(strict_types=1);

namespace App\Http\Controllers\Esg;

use App\Actions\Esg\ExportEsgMeasurementsAction;
use App\Data\Esg\ExportEsgMeasurementsFilterData;
use App\Http\Requests\Esg\ExportEsgMeasurementsRequest;
use App\Models\EsgMeasurement;
use App\Models\Tenant;
use App\Support\Reports\EsgMeasurementExportTable;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class EsgMeasurementPrintController
{
    public function __invoke(ExportEsgMeasurementsRequest $request, ExportEsgMeasurementsAction $export): View
    {
        Gate::authorize('viewAny', EsgMeasurement::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportEsgMeasurementsFilterData(
            indicatorId: $request->integer('indicator') ?: null,
            locationId: $request->integer('location') ?: null,
            unitId: $request->integer('unit') ?: null,
            recordedFrom: (string) ($request->validated('from') ?? ''),
            recordedTo: (string) ($request->validated('to') ?? ''),
            alarmsOnly: $request->boolean('alarms'),
        ));

        return view('reports.print-table', [
            'title' => __('reports.esg.title'),
            'documentTitle' => __('reports.esg.document_title'),
            'tenant' => $tenant,
            'columns' => EsgMeasurementExportTable::columns(),
            'rows' => EsgMeasurementExportTable::rows($result->rows),
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
