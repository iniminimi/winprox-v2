<?php

declare(strict_types=1);

namespace App\Http\Controllers\UnitMeasurements;

use App\Actions\UnitMeasurements\ExportUnitMeasurementsAction;
use App\Data\UnitMeasurements\ExportUnitMeasurementsFilterData;
use App\Http\Requests\UnitMeasurements\ExportUnitMeasurementsRequest;
use App\Models\Tenant;
use App\Models\UnitMeasurement;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class UnitMeasurementPrintController
{
    public function __invoke(ExportUnitMeasurementsRequest $request, ExportUnitMeasurementsAction $export): View
    {
        Gate::authorize('viewAny', UnitMeasurement::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportUnitMeasurementsFilterData(
            locationId: $request->integer('location') ?: null,
            fieldId: $request->integer('field') ?: null,
            search: (string) ($request->validated('q') ?? ''),
        ));

        return view('reports.print-unit-measurements', [
            'tenant' => $tenant,
            'measurements' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
