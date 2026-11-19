<?php

namespace App\Actions\Time;

use App\Data\Time\ClockDisplayPinResult;
use App\Enums\ClockDisplayPinStatus;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bestaande klokcode wijzigen op het scherm: huidige code controleren,
 * daarna de nieuwe zetten. Klokt niet in of uit.
 */
class ChangeClockDisplayPinAction
{
    public function __construct(
        private ConfirmWorkerClockPinAction $confirmPin,
        private SetWorkerClockPinAction $setPin,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        ClockPoint $clockPoint,
        int $workerId,
        string $currentPin,
        string $newPin,
    ): ClockDisplayPinResult {
        $tenantId = (int) $clockPoint->tenant_id;

        $worker = Worker::query()->whereKey($workerId)->first();

        $allowed = $worker !== null
            && (int) $worker->tenant_id === $tenantId
            && $worker->is_active
            && $worker->hasClockPin()
            && $worker->canClockAt($clockPoint->location_id !== null ? (int) $clockPoint->location_id : null);

        if (! $allowed) {
            return new ClockDisplayPinResult(ClockDisplayPinStatus::WorkerNotFound);
        }

        $key = 'clock-display-pin:'.$worker->id;
        $maxAttempts = max(1, (int) config('time.display_pin_max_attempts', 2));
        $decaySeconds = max(10, (int) config('time.display_pin_lockout_seconds', 60));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            $this->audit->record(
                userId: null,
                tenantId: $tenantId,
                action: 'worker.clock_display_pin_blocked_attempt',
                modelType: Worker::class,
                modelId: $worker->id,
                payload: [
                    'clock_point_id' => $clockPoint->id,
                    'retry_after_seconds' => $retryAfter,
                ],
            );

            return new ClockDisplayPinResult(
                ClockDisplayPinStatus::WorkerLocked,
                retryAfterSeconds: $retryAfter,
            );
        }

        $verified = $this->confirmPin->handle($worker, $currentPin, $tenantId);

        if ($verified === null) {
            RateLimiter::hit($key, $decaySeconds);
            $locked = RateLimiter::tooManyAttempts($key, $maxAttempts);
            $this->audit->record(
                userId: null,
                tenantId: $tenantId,
                action: 'worker.clock_display_pin_failed',
                modelType: Worker::class,
                modelId: $worker->id,
                payload: [
                    'clock_point_id' => $clockPoint->id,
                    'locked' => $locked,
                    'via' => 'pin_change',
                ],
            );

            return new ClockDisplayPinResult(
                $locked ? ClockDisplayPinStatus::WorkerLocked : ClockDisplayPinStatus::InvalidPin,
                retryAfterSeconds: $locked ? RateLimiter::availableIn($key) : null,
            );
        }

        RateLimiter::clear($key);
        $this->setPin->handle($worker, $newPin, $tenantId);

        return new ClockDisplayPinResult(
            ClockDisplayPinStatus::PinChanged,
            workerName: $worker->displayName(),
        );
    }
}
