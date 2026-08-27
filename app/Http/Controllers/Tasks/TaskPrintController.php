<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ExportTasksAction;
use App\Data\Tasks\ExportTasksFilterData;
use App\Http\Requests\Tasks\ExportTasksRequest;
use App\Models\Task;
use App\Models\Tenant;
use App\Support\Reports\TaskExportTable;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TaskPrintController
{
    public function __invoke(ExportTasksRequest $request, ExportTasksAction $export): View
    {
        Gate::authorize('viewAny', Task::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $result = $export->handle((int) $tenant->id, new ExportTasksFilterData(
            status: (string) $request->validated('status', ''),
            teamId: $request->integer('team') ?: null,
            priority: (string) ($request->validated('priority') ?? ''),
            search: (string) ($request->validated('q') ?? ''),
            recurringOnly: $request->boolean('recurring'),
        ));

        return view('reports.print-table', [
            'title' => __('reports.tasks.title'),
            'documentTitle' => __('reports.tasks.document_title'),
            'tenant' => $tenant,
            'columns' => TaskExportTable::columns(),
            'rows' => TaskExportTable::rows($result->rows),
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
