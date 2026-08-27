<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issues;

use App\Actions\Issues\ExportIssuesAction;
use App\Data\Issues\ExportIssuesFilterData;
use App\Http\Requests\Issues\ExportIssuesRequest;
use App\Models\Issue;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\IssueExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssueExportController
{
    public function __invoke(ExportIssuesRequest $request, ExportIssuesAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $result = $export->handle((int) Tenancy::id(), $this->filters($request));
        $rows = IssueExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.issues.filename').'-'.now()->format('Y-m-d').'.csv',
            IssueExportTable::columns(),
            $rows,
        );
    }

    private function filters(ExportIssuesRequest $request): ExportIssuesFilterData
    {
        return new ExportIssuesFilterData(
            status: (string) $request->validated('status', ''),
            teamId: $request->integer('team') ?: null,
            search: (string) ($request->validated('q') ?? ''),
            recurringOnly: $request->boolean('recurring'),
            inspectionRoundOnly: $request->boolean('inspection_round'),
            unitId: $request->integer('unit_id') ?: null,
        );
    }
}
