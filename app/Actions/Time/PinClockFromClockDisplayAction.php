<?php

namespace App\Actions\Time;

use App\Data\Time\ClockDisplayPinResult;
use App\Enums\ClockDisplayPinAction;
use App\Enums\ClockDisplayPinStatus;
use App\Enums\ClockSource;
use App\Models\ClockPoint;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

/**
 * No-phone fallback op het klokscherm: worker gekozen via de lettertrie +
 * 4-cijferige PIN → in, uit, pauze start of pauze stop. Optionele `action`
 * (nieuwe firmware); zonder action blijft de oude toggle in/uit.
 */
class PinClockFromClockDisplayAction
{
    public function __construct(
        private ConfirmWorkerClockPinAction $confirmPin,
        private FindOpenWorkShiftForWorkerAction $findOpenShift,
        private ClockInAction $clockIn,
        private ClockOutAction $clockOut,
        private StartWorkBreakAction $startBreak,
        private EndWorkBreakAction $endBreak,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        ClockPoint $clockPoint,
        int $workerId,
        string $pin,
        ?ClockDisplayPinAction $action = null,
    ): ClockDisplayPinResult {
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
        $resolved = $action ?? ($open !== null ? ClockDisplayPinAction::Out : ClockDisplayPinAction::In);

        return match ($resolved) {
            ClockDisplayPinAction::In => $this->punchIn($clockPoint, $worker, $open),
            ClockDisplayPinAction::Out => $this->punchOut($clockPoint, $worker, $open),
            ClockDisplayPinAction::BreakStart => $this->punchBreakStart($clockPoint, $worker, $open),
            ClockDisplayPinAction::BreakEnd => $this->punchBreakEnd($clockPoint, $worker, $open),
        };
    }

    private function punchIn(ClockPoint $clockPoint, Worker $worker, ?WorkShift $open): ClockDisplayPinResult
    {
        if ($open !== null) {
            throw new InvalidArgumentException('shift_already_open');
        }

        $shift = $this->clockIn->handle($worker, $clockPoint, null, null, ClockSource::ClockDisplayPin);
        $this->auditPunch($clockPoint, $worker, $shift->id, 'in');

        return new ClockDisplayPinResult(
            ClockDisplayPinStatus::ClockedIn,
            workerName: $worker->displayName(),
            clockedAt: $shift->clock_in_at?->toIso8601String(),
        );
    }

    private function punchOut(ClockPoint $clockPoint, Worker $worker, ?WorkShift $open): ClockDisplayPinResult
    {
        if ($open === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        $shift = $this->clockOut->handle($worker, $clockPoint, null, ClockSource::ClockDisplayPin);
        $this->auditPunch($clockPoint, $worker, $shift->id, 'out');

        return new ClockDisplayPinResult(
            ClockDisplayPinStatus::ClockedOut,
            workerName: $worker->displayName(),
            clockedAt: $shift->clock_out_at?->toIso8601String(),
        );
    }

    private function punchBreakStart(ClockPoint $clockPoint, Worker $worker, ?WorkShift $open): ClockDisplayPinResult
    {
        if ($open === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        // Geen gsm-binding op het scherm — zelfde als backoffice-pauze.
        $break = $this->startBreak->handle($worker, $open);
        $this->auditPunch($clockPoint, $worker, $open->id, 'break_start');

        return new ClockDisplayPinResult(
            ClockDisplayPinStatus::BreakStarted,
            workerName: $worker->displayName(),
            clockedAt: $break->started_at?->toIso8601String(),
        );
    }

    private function punchBreakEnd(ClockPoint $clockPoint, Worker $worker, ?WorkShift $open): ClockDisplayPinResult
    {
        if ($open === null) {
            throw new InvalidArgumentException('shift_not_open');
        }

        $break = $this->endBreak->handle($worker, $open);
        $this->auditPunch($clockPoint, $worker, $open->id, 'break_end');

        return new ClockDisplayPinResult(
            ClockDisplayPinStatus::BreakEnded,
            workerName: $worker->displayName(),
            clockedAt: $break->ended_at?->toIso8601String(),
        );
    }

    private function auditPunch(ClockPoint $clockPoint, Worker $worker, int $workShiftId, string $direction): void
    {
        $this->audit->record(
            userId: null,
            tenantId: (int) $clockPoint->tenant_id,
            action: 'worker.clock_display_punched',
            modelType: Worker::class,
            modelId: $worker->id,
            payload: [
                'clock_point_id' => $clockPoint->id,
                'work_shift_id' => $workShiftId,
                'direction' => $direction,
            ],
        );
    }

    private function rateKey(Worker $worker): string
    {
        return 'clock-display-pin:'.$worker->id;
    }
}
