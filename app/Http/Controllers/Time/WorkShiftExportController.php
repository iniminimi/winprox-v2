<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\ExportWorkShiftsAction;
use App\Models\InternalTeam;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkShiftExportController
{
    public function __invoke(Request $request, ExportWorkShiftsAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', WorkShift::class);

        $tenantId = (int) Tenancy::id();
        $from = $request->query('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->query('to') ? Carbon::parse((string) $request->query('to')) : null;
        $teamId = $request->integer('team') ?: null;
        $workerId = $request->integer('worker') ?: null;
        $clockPointId = $request->integer('clock_point') ?: null;

        $rows = $export->handle($tenantId, $from, $to, $teamId, $workerId, $clockPointId);

        $filename = 'time-shifts-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('time.export.columns.date'),
                __('time.export.columns.worker'),
                __('time.export.columns.team'),
                __('time.export.columns.clock_in'),
                __('time.export.columns.clock_out'),
                __('time.export.columns.break_minutes'),
                __('time.export.columns.worked'),
                __('time.export.columns.clock_in_at'),
                __('time.export.columns.clock_out_at'),
                __('time.export.columns.status'),
                __('time.export.columns.tasks'),
            ], ';');

            foreach ($rows as $shift) {
                $taskLabels = $shift->taskLogs
                    ->map(fn ($log) => $log->task?->displayDescription() ?: '')
                    ->filter()
                    ->implode(' | ');

                fputcsv($handle, [
                    $shift->clock_in_at->format('Y-m-d'),
                    $shift->worker?->displayName() ?? '',
                    $shift->team?->name ?? '',
                    $shift->clock_in_at->format('H:i'),
                    $shift->clock_out_at?->format('H:i') ?? '',
                    $shift->total_break_minutes,
                    $shift->netWorkMinutes(),
                    $shift->clockInClockPoint?->name ?? '',
                    $shift->clockOutClockPoint?->name ?? '',
                    $shift->status->value,
                    $taskLabels,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
