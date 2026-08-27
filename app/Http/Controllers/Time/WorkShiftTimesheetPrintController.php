<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\ExportWorkShiftsAction;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkShiftTimesheetPrintController
{
    public function __invoke(Request $request, ExportWorkShiftsAction $export): View
    {
        Gate::authorize('viewAny', WorkShift::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : now()->endOfDay();

        $result = $export->handle(
            (int) $tenant->id,
            $from,
            $to,
            $request->integer('team') ?: null,
            $request->integer('worker') ?: null,
            $request->integer('clock_point') ?: null,
        );

        $shifts = $result->rows;
        $totalNetMinutes = (int) $shifts->sum(fn ($shift) => $shift->netWorkMinutes());

        return view('time.print', [
            'tenant' => $tenant,
            'shifts' => $shifts,
            'from' => $from,
            'to' => $to,
            'totalNetMinutes' => $totalNetMinutes,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
