<?php

namespace App\Actions\Time;

use App\Data\Time\LeaveBalance;
use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\WorkerLeaveEntitlement;
use App\Support\Time\TimeModuleAccess;
use Carbon\CarbonImmutable;

class WorkerLeaveBalanceAction
{
    /**
     * @param  list<int>  $workerIds
     * @return array<int, LeaveBalance>
     */
    public function handle(Tenant $tenant, array $workerIds, int $year): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $workerIds = array_values(array_unique(array_map('intval', $workerIds)));
        $balances = [];
        foreach ($workerIds as $workerId) {
            $balances[$workerId] = new LeaveBalance(null, 0, 0);
        }
        if ($workerIds === []) {
            return $balances;
        }

        $start = sprintf('%d-01-01', $year);
        $end = sprintf('%d-12-31', $year);

        $entitlements = WorkerLeaveEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->where('year', $year)
            ->get()
            ->keyBy(fn (WorkerLeaveEntitlement $row) => (int) $row->worker_id);

        $approved = [];
        $shifts = PlannedShift::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->where('kind', ShiftTypeKind::Leave)
            ->whereDate('work_date', '>=', $start)
            ->whereDate('work_date', '<=', $end)
            ->get(['worker_id', 'work_date', 'start_time', 'end_time', 'day_portion']);

        foreach ($shifts as $shift) {
            $workerId = (int) $shift->worker_id;
            $date = $shift->work_date->toDateString();
            $approved[$workerId][$date] = min(2, ($approved[$workerId][$date] ?? 0) + $this->shiftHalves($shift));
        }

        $planned = [];
        $requests = AbsenceRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('worker_id', $workerIds)
            ->where('kind', ShiftTypeKind::Leave)
            ->where('status', AbsenceRequestStatus::Pending)
            ->whereDate('date_from', '<=', $end)
            ->whereDate('date_to', '>=', $start)
            ->get();

        foreach ($requests as $request) {
            $workerId = (int) $request->worker_id;
            foreach ($this->datesInYear($request, $year) as $date) {
                if (($approved[$workerId][$date] ?? 0) > 0) {
                    continue;
                }
                $planned[$workerId][$date] = min(
                    2,
                    ($planned[$workerId][$date] ?? 0) + $this->requestDayHalves($request),
                );
            }
        }

        foreach ($workerIds as $workerId) {
            $row = $entitlements->get($workerId);
            $balances[$workerId] = new LeaveBalance(
                $row instanceof WorkerLeaveEntitlement ? $this->storedHalves($row->days) : null,
                array_sum($approved[$workerId] ?? []),
                array_sum($planned[$workerId] ?? []),
            );
        }

        return $balances;
    }

    /**
     * @return list<array{year: int, balance: LeaveBalance, request_halves: int}>
     */
    public function forRequest(Tenant $tenant, AbsenceRequest $request): array
    {
        if ($request->kind !== ShiftTypeKind::Leave) {
            return [];
        }

        $from = CarbonImmutable::parse($request->date_from->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($request->date_to->toDateString())->startOfDay();
        $rows = [];
        for ($year = (int) $from->year; $year <= (int) $to->year; $year++) {
            $halves = 0;
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                if ((int) $day->year !== $year) {
                    continue;
                }
                $halves += $this->requestDayHalves($request);
            }
            $rows[] = [
                'year' => $year,
                'balance' => $this->handle($tenant, [(int) $request->worker_id], $year)[(int) $request->worker_id],
                'request_halves' => $halves,
            ];
        }

        return $rows;
    }

    /**
     * @return array{short: string, title: string}|null
     */
    public function rosterMark(LeaveBalance $balance): ?array
    {
        $remaining = $balance->remainingHalves();
        if ($remaining === null || $balance->entitlementHalves === null) {
            return null;
        }

        return [
            'short' => __('time.leave_balance.short', [
                'days' => LeaveBalance::formatHalves($remaining),
            ]),
            'title' => __('time.leave_balance.title', [
                'entitlement' => LeaveBalance::formatHalves($balance->entitlementHalves),
                'approved' => LeaveBalance::formatHalves($balance->approvedHalves),
                'planned' => LeaveBalance::formatHalves($balance->plannedHalves),
                'remaining' => LeaveBalance::formatHalves($remaining),
            ]),
        ];
    }

    private function shiftHalves(PlannedShift $shift): int
    {
        if ($this->isHalf($shift->day_portion) || ($shift->start_time !== null && $shift->end_time !== null)) {
            return 1;
        }

        return 2;
    }

    private function requestDayHalves(AbsenceRequest $request): int
    {
        return $this->isHalf($request->day_portion) ? 1 : 2;
    }

    /**
     * @return list<string>
     */
    private function datesInYear(AbsenceRequest $request, int $year): array
    {
        $start = CarbonImmutable::parse(max(
            $request->date_from->toDateString(),
            sprintf('%d-01-01', $year),
        ))->startOfDay();
        $end = CarbonImmutable::parse(min(
            $request->date_to->toDateString(),
            sprintf('%d-12-31', $year),
        ))->startOfDay();

        $dates = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    private function storedHalves(mixed $days): int
    {
        return (int) round(((float) $days) * 2);
    }

    private function isHalf(mixed $portion): bool
    {
        return $portion !== null && (int) round(((float) $portion) * 2) === 1;
    }
}
