<?php

namespace App\Actions\Time;

use App\Enums\PlannedShiftStatus;
use App\Models\ClockPoint;
use App\Models\PlannedShift;
use App\Models\Worker;
use App\Models\WorkShift;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Of een aangemelde uitvoerder nu de bluetooth-vraag mag zien.
 * Vestiging met klokscherm in BLE-modus; in/uit rond de dienstgrenzen,
 * of pauze starten/stoppen terwijl de dienst open is.
 */
class ResolveWorkerBlePunchPromptAction
{
    public const WINDOW_MINUTES = 15;

    /**
     * @return array{direction: string, planned_shift_id: int}|null
     */
    public function handle(Worker $worker, ?WorkShift $openShift, ?CarbonInterface $now = null, ?int $portalLocationId = null): ?array
    {
        if ($portalLocationId === null) {
            return null;
        }

        $tenant = $worker->relationLoaded('tenant') ? $worker->tenant : $worker->tenant()->first();
        if ($tenant !== null && $tenant->checkmateMode()) {
            return null;
        }

        $now = Carbon::parse($now ?? now())->timezone((string) config('app.timezone'));
        $clockedIn = $openShift !== null && $openShift->clock_out_at === null;

        $plans = PlannedShift::query()
            ->where('worker_id', $worker->id)
            ->where('status', PlannedShiftStatus::Published)
            ->whereDate('work_date', '>=', $now->copy()->subDay()->toDateString())
            ->whereDate('work_date', '<=', $now->toDateString())
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereNotNull('location_id')
            ->get();

        $best = null;
        foreach ($plans as $plan) {
            if ($plan->kind->isAbsence() || (int) $plan->location_id !== $portalLocationId) {
                continue;
            }
            if (! $this->locationHasDisplay((int) $plan->location_id)) {
                continue;
            }

            $start = $this->at((string) $plan->work_date->toDateString(), (string) $plan->start_time, $now);
            $end = $this->at((string) $plan->work_date->toDateString(), (string) $plan->end_time, $now);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->copy()->addDay();
            }

            $direction = null;
            if (! $clockedIn && $this->inside($now, $start)) {
                $direction = 'in';
            } elseif ($clockedIn && $this->inside($now, $end)) {
                $direction = 'out';
            } elseif ($clockedIn && $openShift !== null && $openShift->openBreak !== null) {
                $direction = 'break_end';
            } elseif ($clockedIn && $openShift !== null && $openShift->openBreak === null
                && (bool) ($openShift->team?->required_break_minutes ?? 0) > 0
                && $openShift->breaks->isEmpty()) {
                $direction = 'break_start';
            }
            if ($direction === null) {
                continue;
            }

            $best = [
                'direction' => $direction,
                'planned_shift_id' => (int) $plan->id,
            ];
            if (in_array($direction, ['out', 'break_end'], true)) {
                break;
            }
        }

        return $best;
    }

    private function locationHasDisplay(int $locationId): bool
    {
        return ClockPoint::query()
            ->where('location_id', $locationId)
            ->where('is_active', true)
            ->get()
            ->contains(fn (ClockPoint $point): bool => $point->displayUsesBle());
    }

    private function at(string $date, string $time, Carbon $now): Carbon
    {
        return Carbon::parse($date.' '.$time, $now->timezone);
    }

    private function inside(Carbon $now, Carbon $boundary): bool
    {
        return $now->greaterThanOrEqualTo($boundary->copy()->subMinutes(self::WINDOW_MINUTES))
            && $now->lessThan($boundary->copy()->addMinutes(self::WINDOW_MINUTES));
    }
}
