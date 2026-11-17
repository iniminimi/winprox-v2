<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\SendWorkerPushAction;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Eén push per open dienst, 15 minuten na het einde van het laatste gepubliceerde
 * werkblok van die inklokdag. Zonder rooster geen gisbericht. Geen abonnement
 * nog: de volgende run probeert opnieuw zolang de dienst open is.
 */
class RemindForgottenClockOutsAction
{
    public function __construct(
        private SendWorkerPushAction $send,
        private CreateNotificationAction $createNotification,
    ) {}

    public function handle(?Carbon $now = null): int
    {
        $now = ($now ?? now())->copy();
        $tolerance = max(0, (int) config('time.roster_attendance_tolerance_minutes', 15));
        $sent = 0;

        Tenant::query()->where('has_time_module', true)->orderBy('id')->each(function (Tenant $tenant) use ($now, $tolerance, &$sent): void {
            Tenancy::actAs($tenant->id);

            $shifts = WorkShift::query()->open()->orderBy('id')->get();
            if ($shifts->isEmpty()) {
                return;
            }

            $dates = $shifts
                ->flatMap(fn (WorkShift $shift) => $shift->clock_in_at === null ? [] : [
                    $shift->clock_in_at->toDateString(),
                    $shift->clock_in_at->copy()->subDay()->toDateString(),
                ])
                ->unique()
                ->values()
                ->all();

            if ($dates === []) {
                return;
            }

            $workerIds = $shifts->pluck('worker_id')->unique()->values()->all();
            $from = min($dates);
            $to = max($dates);

            $plans = PlannedShift::query()
                ->whereIn('worker_id', $workerIds)
                ->whereDate('work_date', '>=', $from)
                ->whereDate('work_date', '<=', $to)
                ->where('status', PlannedShiftStatus::Published)
                ->where('kind', ShiftTypeKind::Work)
                ->get()
                ->groupBy('worker_id');

            foreach ($shifts as $shift) {
                $workerPlans = $plans->get($shift->worker_id)
                    ?? $plans->get((string) $shift->worker_id)
                    ?? collect();
                if ($this->remind($tenant, $shift, $workerPlans, $now, $tolerance)) {
                    $sent++;
                }
            }
        });

        Tenancy::forget();

        return $sent;
    }

    /**
     * @param  Collection<int, PlannedShift>  $plans
     */
    private function remind(Tenant $tenant, WorkShift $shift, Collection $plans, Carbon $now, int $tolerance): bool
    {
        $clockIn = $shift->clock_in_at;
        if (! $clockIn instanceof Carbon) {
            return false;
        }

        $worker = Worker::query()->whereKey($shift->worker_id)->first();
        if (! $worker instanceof Worker || ! $worker->is_active) {
            return false;
        }

        $latestEnd = $this->latestEnd($plans, $clockIn);
        if (! $latestEnd instanceof Carbon || $now->lt($latestEnd->copy()->addMinutes($tolerance))) {
            return false;
        }

        $reference = (string) $shift->id;
        $already = $worker->notifications()
            ->where('type', WorkerNotificationType::ClockOutReminder)
            ->where('reference_id', $reference)
            ->exists();
        if ($already) {
            return false;
        }

        $result = $this->send->handle(
            (int) $tenant->id,
            (int) $worker->id,
            'time.portal.push.clock_out_title',
            'time.portal.push.clock_out_body',
            [],
            route('public.time-portal.home', ['tenant' => $tenant->id]),
            'clock_out_reminder:'.$reference,
        );

        if ($result !== SendWorkerPushAction::SENT) {
            return false;
        }

        $this->createNotification->recordOnce(
            $tenant,
            $worker,
            WorkerNotificationType::ClockOutReminder,
            $reference,
        );

        return true;
    }

    /**
     * @param  Collection<int, PlannedShift>  $plans
     */
    private function latestEnd(Collection $plans, Carbon $clockIn): ?Carbon
    {
        $latest = null;
        $clockDay = $clockIn->toDateString();

        foreach ($plans as $plan) {
            if ($plan->start_time === null || $plan->end_time === null) {
                continue;
            }

            $startMinutes = ShiftType::timeToMinutes((string) $plan->start_time);
            $endMinutes = ShiftType::timeToMinutes((string) $plan->end_time);
            $start = Carbon::parse($plan->work_date)->startOfDay()->addMinutes($startMinutes);
            $end = Carbon::parse($plan->work_date)->startOfDay()->addMinutes($endMinutes);
            if ($endMinutes <= $startMinutes) {
                $end->addDay();
            }

            if ($end->lessThanOrEqualTo($clockIn)) {
                continue;
            }

            $onClockDay = $plan->work_date->toDateString() === $clockDay;
            $coversClockIn = $start->lessThanOrEqualTo($clockIn) && $end->greaterThan($clockIn);
            if (! $onClockDay && ! $coversClockIn) {
                continue;
            }

            if ($latest === null || $end->greaterThan($latest)) {
                $latest = $end;
            }
        }

        return $latest;
    }
}
