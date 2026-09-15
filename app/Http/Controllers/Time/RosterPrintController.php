<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RosterPrintController
{
    public function __invoke(
        Request $request,
        ListRosterWeekAction $list,
        ListShiftTypesAction $listTypes,
    ): View {
        Gate::authorize('viewAny', PlannedShift::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $period = $request->query('view') === 'month' ? 'month' : 'week';
        $weekStart = $request->string('week')->toString();
        if ($weekStart === '') {
            $weekStart = $period === 'month'
                ? now()->startOfMonth()->toDateString()
                : now()->startOfWeek(Carbon::MONDAY)->toDateString();
        }

        $locationId = $request->integer('location') ?: null;
        $groupUnitIds = null;
        if ($locationId !== null) {
            $groups = $request->query('groups');
            $groupUnitIds = is_array($groups)
                ? array_values(array_map('intval', $groups))
                : null;
        }

        $snapshot = $list->handle(
            (int) $tenant->id,
            $weekStart,
            $request->integer('team') ?: null,
            $request->user(),
            $period,
            $request->boolean('weekends'),
            $locationId,
            $groupUnitIds,
            $locationId === null ? true : $request->boolean('ungrouped', true),
        );

        $periodLabel = $period === 'month'
            ? $snapshot->monthLabel
            : __('time.schedule.week_numbered', [
                'number' => Carbon::parse($snapshot->weekStart)->isoWeek(),
            ]);

        $legendTypes = $listTypes->handle((int) $tenant->id, true);

        return view('time.roster-print', [
            'tenant' => $tenant,
            'snapshot' => $snapshot,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'legendTypes' => $legendTypes,
        ]);
    }
}
