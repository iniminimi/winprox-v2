<?php

declare(strict_types=1);

namespace App\Http\Controllers\Issues;

use App\Actions\Issues\ExportIssuesAction;
use App\Data\Issues\ExportIssuesFilterData;
use App\Http\Requests\Issues\ExportIssuesRequest;
use App\Models\Issue;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class IssuePrintController
{
    public function __invoke(ExportIssuesRequest $request, ExportIssuesAction $export): View
    {
        Gate::authorize('viewAny', Issue::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportIssuesFilterData(
            status: (string) $request->validated('status', ''),
            teamId: $request->integer('team') ?: null,
            search: (string) ($request->validated('q') ?? ''),
            recurringOnly: $request->boolean('recurring'),
            inspectionRoundOnly: $request->boolean('inspection_round'),
            unitId: $request->integer('unit_id') ?: null,
        ));

        return view('reports.print-issues', [
            'tenant' => $tenant,
            'issues' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
