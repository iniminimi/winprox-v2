<?php

declare(strict_types=1);

namespace App\Http\Controllers\UnitChecks;

use App\Actions\UnitChecks\ExportUnitChecksAction;
use App\Data\UnitChecks\ExportUnitChecksFilterData;
use App\Http\Requests\UnitChecks\ExportUnitChecksRequest;
use App\Models\Tenant;
use App\Models\UnitCheck;
use App\Support\Reports\UnitCheckExportTable;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class UnitCheckPrintController
{
    public function __invoke(ExportUnitChecksRequest $request, ExportUnitChecksAction $export): View
    {
        Gate::authorize('viewAny', UnitCheck::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportUnitChecksFilterData(
            result: (string) ($request->validated('result') ?? 'all'),
            locationId: $request->integer('location') ?: null,
        ));

        return view('reports.print-table', [
            'title' => __('reports.unit_checks.title'),
            'documentTitle' => __('reports.unit_checks.document_title'),
            'tenant' => $tenant,
            'columns' => UnitCheckExportTable::columns(),
            'rows' => UnitCheckExportTable::rows($result->rows),
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
