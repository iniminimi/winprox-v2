<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\ClockPointQrToken;
use App\Support\Audit\AuditRecorder;
use App\Support\Portal\WorkerDeviceSession;

class LogBlockedClockPointQrAttemptAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(ClockPoint $clockPoint, string $token, ?ClockPointQrToken $historyToken = null): void
    {
        $deviceWorker = WorkerDeviceSession::workerFromDeviceCookie();

        $this->audit->record(
            userId: null,
            tenantId: (int) $clockPoint->tenant_id,
            action: 'clock_point.qr_blocked',
            modelType: ClockPoint::class,
            modelId: $clockPoint->id,
            payload: [
                'clock_point_id' => $clockPoint->id,
                'qr_token' => $token,
                'history_token_id' => $historyToken?->id,
                'worker_id' => $deviceWorker?->id,
            ],
        );
    }
}
