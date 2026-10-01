<?php

namespace App\Actions\Time;

use App\Data\Time\ClockDisplayPinResult;
use App\Enums\ClockDisplayPinStatus;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Worker zonder PIN zet zijn code rechtstreeks op het klokscherm
 * (naam gekozen via lettertrie → 4 cijfers ×2). Na het zetten prikt
 * dezelfde flow meteen in/uit via PinClockFromClockDisplayAction.
 * Geldig enkel zolang de worker nog géén PIN heeft — een bestaande
 * PIN kan hier niet overschreven worden.
 */
class SetupClockDisplayPinAction
{
    public function __construct(
        private SetWorkerClockPinAction $setPin,
        private PinClockFromClockDisplayAction $pinClock,
        private AuditRecorder $audit,
    ) {}

    public function handle(ClockPoint $clockPoint, int $workerId, string $pin): ClockDisplayPinResult
    {
        $tenantId = (int) $clockPoint->tenant_id;

        $worker = Worker::query()->whereKey($workerId)->with('team')->first();

        // Zelfde scope als de pin-klok: buiten bereik → not_found.
        $allowed = $worker !== null
            && (int) $worker->tenant_id === $tenantId
            && $worker->is_active
            && $worker->canClockAt($clockPoint->location_id !== null ? (int) $clockPoint->location_id : null);

        if (! $allowed) {
            throw new InvalidArgumentException('worker_not_found');
        }

        if ($worker->hasClockPin()) {
            throw new InvalidArgumentException('pin_already_set');
        }

        $worker = $this->setPin->handle($worker, $pin, $tenantId);

        $this->audit->record(
            userId: null,
            tenantId: $tenantId,
            action: 'worker.clock_display_pin_set',
            modelType: Worker::class,
            modelId: $worker->id,
            payload: [
                'clock_point_id' => $clockPoint->id,
                'via' => 'clock_display',
            ],
        );

        // PIN staat → meteen prikken via de bestaande lockout/verify-flow.
        return $this->pinClock->handle($clockPoint, $workerId, $pin);
    }
}
