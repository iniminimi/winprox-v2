<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\DispatchWorkerPushAction;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Models\AbsenceRequest;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Push\WorkerPushScreen;

class NotifyWorkerAbsenceApprovedAction
{
    public function __construct(
        private CreateNotificationAction $createNotification,
        private DispatchWorkerPushAction $dispatchPush,
    ) {}

    public function handle(Tenant $tenant, Worker $worker, AbsenceRequest $request, bool $approved = true): void
    {
        if ((int) $request->tenant_id !== (int) $tenant->id || (int) $request->worker_id !== (int) $worker->id) {
            return;
        }

        if (! $request->kind->isRequestableAbsence()) {
            return;
        }

        $row = $this->createNotification->recordOnce(
            $tenant,
            $worker,
            $approved ? WorkerNotificationType::AbsenceApproved : WorkerNotificationType::AbsenceRejected,
            (string) $request->id,
        );
        if (! $row->wasRecentlyCreated) {
            return;
        }

        $from = $request->date_from->format('d-m-Y');
        $to = $request->date_to->format('d-m-Y');
        $slug = match ($request->kind) {
            ShiftTypeKind::Leave => 'leave',
            ShiftTypeKind::Recup => 'recup',
            ShiftTypeKind::Sick => 'sick',
            default => 'other',
        };
        $sameDay = $from === $to;
        $prefix = 'time.portal.push.absence_'.$slug.($approved ? '' : '_rejected');

        $this->dispatchPush->handle(
            $tenant,
            $worker,
            $prefix.'_title',
            $prefix.($sameDay ? '_body' : '_body_range'),
            $sameDay ? ['date' => $from] : ['from' => $from, 'to' => $to],
            WorkerPushScreen::ABSENCE,
            ($approved ? 'absence_approved:' : 'absence_rejected:').$request->id,
        );
    }
}
