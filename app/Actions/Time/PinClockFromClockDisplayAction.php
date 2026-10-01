<?php

namespace App\Actions\Time;

use App\Data\Time\ClockDisplayPinResult;
use App\Enums\ClockDisplayPinStatus;
use App\Enums\ClockSource;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * No-phone fallback op het klokscherm: worker gekozen via de lettertrie +
 * 4-cijferige PIN → in- of uitklokken. De server is autoriteit: PIN-verify
 * via de bestaande hash, lockout per worker (2 fouten → korte blokkade) en
 * audit van elke poging. Het device stuurt enkel worker_id + pin.
 */
class PinClockFromClockDisplayAction
{
    public function __construct(
        private ConfirmWorkerClockPinAction $confirmPin,
        private FindOpenWorkShiftForWorkerAction $findOpenShift,
        private ClockInAction $clockIn,
        private ClockOutAction $clockOut,
        private AuditRecorder $audit,
    ) {}

    public function handle(ClockPoint $clockPoint, int $workerId, string $pin): ClockDisplayPinResult
    {
        $tenantId = (int) $clockPoint->tenant_id;

        $worker = Worker::query()->whereKey($workerId)->with('team')->first();

        // Onzichtbaar maken wat buiten scope ligt: bestaat niet, andere tenant,
        // inactief, geen PIN of niet toegelaten op deze locatie → not_found.
        $allowed = $worker !== null
            && (int) $worker->tenant_id === $tenantId
            && $worker->is_active
            && $worker->hasClockPin()
            && $worker->canClockAt($clockPoint->location_id !== null ? (int) $clockPoint->location_id : null);

        if (! $allowed) {
            return new ClockDisplayPinResult(ClockDisplayPinStatus::WorkerNotFound);
        }

        $key = $this->rateKey($worker);
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

        $verified = $this->confirmPin->handle($worker, $pin, $tenantId);

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
                ],
            );

            return new ClockDisplayPinResult(
                $locked ? ClockDisplayPinStatus::WorkerLocked : ClockDisplayPinStatus::InvalidPin,
                retryAfterSeconds: $locked ? RateLimiter::availableIn($key) : null,
            );
        }

        RateLimiter::clear($key);

        $open = $this->findOpenShift->handle($worker);
        $shift = $open !== null
            ? $this->clockOut->handle($worker, $clockPoint, null, ClockSource::ClockDisplayPin)
            : $this->clockIn->handle($worker, $clockPoint, null, null, ClockSource::ClockDisplayPin);

        $this->audit->record(
            userId: null,
            tenantId: $tenantId,
            action: 'worker.clock_display_punched',
            modelType: Worker::class,
            modelId: $worker->id,
            payload: [
                'clock_point_id' => $clockPoint->id,
                'work_shift_id' => $shift->id,
                'direction' => $open !== null ? 'out' : 'in',
            ],
        );

        return new ClockDisplayPinResult(
            $open !== null ? ClockDisplayPinStatus::ClockedOut : ClockDisplayPinStatus::ClockedIn,
            workerName: $worker->displayName(),
            clockedAt: $shift->clock_out_at?->toIso8601String() ?? $shift->clock_in_at?->toIso8601String(),
        );
    }

    private function rateKey(Worker $worker): string
    {
        return 'clock-display-pin:'.$worker->id;
    }
}
