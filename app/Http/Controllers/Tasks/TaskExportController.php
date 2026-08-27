<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Actions\Tasks\ExportTasksAction;
use App\Data\Tasks\ExportTasksFilterData;
use App\Http\Requests\Tasks\ExportTasksRequest;
use App\Models\Task;
use App\Support\Reports\CsvStreamer;
use App\Support\Reports\TaskExportTable;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskExportController
{
    public function __invoke(ExportTasksRequest $request, ExportTasksAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', Task::class);

        $result = $export->handle((int) Tenancy::id(), $this->filters($request));
        $rows = TaskExportTable::rows($result->rows);

        if ($result->truncated) {
            $rows = $rows->prepend([
                __('reports.truncated', ['limit' => $result->limit]),
                '', '', '', '', '', '', '', '', '', '',
            ]);
        }

        return CsvStreamer::download(
            __('reports.tasks.filename').'-'.now()->format('Y-m-d').'.csv',
            TaskExportTable::columns(),
            $rows,
        );
    }

    private function filters(ExportTasksRequest $request): ExportTasksFilterData
    {
        return new ExportTasksFilterData(
            status: (string) $request->validated('status', ''),
            teamId: $request->integer('team') ?: null,
            priority: (string) ($request->validated('priority') ?? ''),
            search: (string) ($request->validated('q') ?? ''),
            recurringOnly: $request->boolean('recurring'),
        );
    }
}
