<?php

namespace App\Actions\Team;

use App\Http\Requests\Team\UpdateWorkerRequest;
use App\Models\Worker;
use App\Models\WorkerWeekdayMinute;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vervangt de zeven normale weekdagminuten van een uitvoerder.
 * De som moet de huidige weeknorm zijn. Ontbrekende dagen worden niet als 0 gelezen.
 */
class SyncWorkerWeekdayMinutesAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<int|string, int|string|null>  $minutesByWeekday
     */
    public function handle(int $tenantId, Worker $worker, array $minutesByWeekday, ?int $actorUserId = null): void
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'worker' => __('time.schedule.month_norm.errors.worker_tenant'),
            ]);
        }

        if ($worker->weekly_contract_minutes === null) {
            throw ValidationException::withMessages([
                'minutes' => __('time.schedule.month_norm.errors.pattern_no_norm'),
            ]);
        }

        $minutes = [];
        foreach ($minutesByWeekday as $weekday => $value) {
            if (! is_numeric($weekday) || ! is_numeric($value)) {
                continue;
            }
            $day = (int) $weekday;
            if ($day < 1 || $day > 7) {
                continue;
            }
            $amount = (int) $value;
            if ($amount < 0 || $amount > UpdateWorkerRequest::WEEKLY_CONTRACT_MINUTES_MAX) {
                throw ValidationException::withMessages([
                    'minutes' => __('time.schedule.month_norm.errors.pattern_sum'),
                ]);
            }
            $minutes[$day] = $amount;
        }

        if (count($minutes) !== 7) {
            throw ValidationException::withMessages([
                'minutes' => __('time.schedule.month_norm.errors.pattern_weekdays'),
            ]);
        }

        $sum = array_sum($minutes);
        if ($sum !== (int) $worker->weekly_contract_minutes) {
            throw ValidationException::withMessages([
                'minutes' => __('time.schedule.month_norm.errors.pattern_sum'),
            ]);
        }

        DB::transaction(function () use ($worker, $tenantId, $minutes): void {
            $worker->weekdayMinutes()->delete();
            foreach ($minutes as $weekday => $amount) {
                WorkerWeekdayMinute::query()->create([
                    'tenant_id' => $tenantId,
                    'worker_id' => $worker->id,
                    'weekday' => $weekday,
                    'minutes' => $amount,
                ]);
            }
        });

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.weekday_minutes_synced',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [
                'weekly_contract_minutes' => (int) $worker->weekly_contract_minutes,
                'minutes' => $minutes,
            ],
        );
    }
}
