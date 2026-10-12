<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Models\WorkerHolidayAgreement;
use App\Support\Audit\AuditRecorder;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DeleteWorkerHolidayAgreementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(int $tenantId, Worker $worker, string $holidayDate, ?int $actorUserId = null): void
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'worker' => __('time.schedule.month_norm.errors.worker_tenant'),
            ]);
        }

        $holidayDate = Carbon::parse($holidayDate)->toDateString();
        WorkerHolidayAgreement::query()
            ->where('worker_id', $worker->id)
            ->whereDate('holiday_date', $holidayDate)
            ->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.holiday_agreement_deleted',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: ['holiday_date' => $holidayDate],
        );
    }
}
