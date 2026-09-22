<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Events\Time\AbsenceCancelled;
use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class CancelAbsenceRequestAction
{
    public function __construct(
        private RestoreAbsenceDaysToRosterAction $restore,
    ) {}

    public function handle(Tenant $tenant, Worker $worker, AbsenceRequest $request): AbsenceRequest
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $request->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if ((int) $request->worker_id !== (int) $worker->id) {
            throw new InvalidArgumentException('not_owner');
        }

        if (! $request->workerMayWithdraw()) {
            throw new InvalidArgumentException(
                $request->status === AbsenceRequestStatus::Approved
                    ? 'already_started'
                    : 'not_cancellable'
            );
        }

        if ($request->status === AbsenceRequestStatus::Approved) {
            $this->restore->handle($tenant, $request);
        }

        $request->status = AbsenceRequestStatus::Cancelled;
        $request->save();

        AbsenceCancelled::dispatch(
            (int) $tenant->id,
            (int) $request->id,
            (int) $worker->id,
            $request->kind->value,
            $request->date_from->toDateString(),
            $request->date_to->toDateString(),
        );

        return $request->fresh(['worker', 'shiftType']) ?? $request;
    }
}
