<?php

declare(strict_types=1);

namespace App\Http\Controllers\UnitChecks;

use App\Actions\UnitChecks\ExportUnitChecksAction;
use App\Data\UnitChecks\ExportUnitChecksFilterData;
use App\Http\Requests\UnitChecks\ExportUnitChecksRequest;
use App\Models\UnitCheck;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\UnitCheckExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnitCheckExportController
{
    public function __invoke(ExportUnitChecksRequest $request, ExportUnitChecksAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', UnitCheck::class);

        $result = $export->handle((int) Tenancy::id(), new ExportUnitChecksFilterData(
            result: (string) ($request->validated('result') ?? 'all'),
            locationId: $request->integer('location') ?: null,
        ));

        $rows = UnitCheckExportTable::rows($result->rows);
        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.unit_checks.filename').'-'.now()->format('Y-m-d').'.csv',
            UnitCheckExportTable::columns(),
            $rows,
        );
    }
}
