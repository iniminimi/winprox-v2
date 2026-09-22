<?php

namespace App\Actions\Time;

use App\Data\Time\DecideAbsenceRequestData;
use App\Enums\AbsenceRequestStatus;
use App\Events\Time\AbsenceApproved;
use App\Events\Time\AbsenceRejected;
use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Time\TimeModuleAccess;
use App\Support\Validation\TextDescriptionLimits;
use InvalidArgumentException;

class DecideAbsenceRequestAction
{
    public function __construct(
        private ApplyAbsenceDaysToRosterAction $applyAbsenceDays,
    ) {}

    public function handle(
        Tenant $tenant,
        AbsenceRequest $request,
        DecideAbsenceRequestData $data,
        User $actor,
    ): AbsenceRequest {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $request->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if (! $request->status->isPending()) {
            throw new InvalidArgumentException('not_pending');
        }

        $reason = trim($data->reason);
        if (! $data->approve && mb_strlen($reason) < 3) {
            throw new InvalidArgumentException('reason_required');
        }

        $reason = $reason === '' ? '' : mb_substr($reason, 0, TextDescriptionLimits::MAX);
        $replaced = [];

        if ($data->approve) {
            $replaced = $this->applyAbsenceDays->handle($tenant, $request);
            $request->status = AbsenceRequestStatus::Approved;
            $request->replaced_shifts = $replaced;
        } else {
            $request->status = AbsenceRequestStatus::Rejected;
        }

        $request->decision_description = $reason === '' ? null : $reason;
        $request->decided_by_user_id = $actor->id;
        $request->decided_at = now();
        $request->save();

        if ($data->approve) {
            AbsenceApproved::dispatch(
                (int) $tenant->id,
                (int) $request->id,
                (int) $request->worker_id,
                $request->kind->value,
                $request->date_from->toDateString(),
                $request->date_to->toDateString(),
                $reason,
                (int) $actor->id,
                $replaced,
            );
        } else {
            AbsenceRejected::dispatch(
                (int) $tenant->id,
                (int) $request->id,
                (int) $request->worker_id,
                $request->kind->value,
                $request->date_from->toDateString(),
                $request->date_to->toDateString(),
                $reason,
                (int) $actor->id,
            );
        }

        return $request->fresh(['worker', 'shiftType', 'decidedBy']) ?? $request;
    }
}
