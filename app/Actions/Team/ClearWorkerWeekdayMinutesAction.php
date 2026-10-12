<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class ClearWorkerWeekdayMinutesAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $tenantId, Worker $worker, ?int $actorUserId = null): void
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'worker' => __('time.schedule.month_norm.errors.worker_tenant'),
            ]);
        }

        $worker->weekdayMinutes()->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.weekday_minutes_cleared',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [],
        );
    }
}
