<?php

namespace App\Actions\Team;

use App\Enums\HolidayAgreementEffect;
use App\Models\BelgianPublicHoliday;
use App\Models\Worker;
use App\Models\WorkerHolidayAgreement;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\BelgianPublicHolidayCalendar;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Bewaart één feestdagafspraak. Conflicten met andere afspraken van dezelfde
 * uitvoerder worden geweigerd. Een datum buiten de kalenderdekking is geen geldige afspraak.
 */
class SaveWorkerHolidayAgreementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        int $tenantId,
        Worker $worker,
        string $holidayDate,
        HolidayAgreementEffect $effect,
        ?string $replacementDate,
        ?int $actorUserId = null,
    ): WorkerHolidayAgreement {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'worker' => __('time.schedule.month_norm.errors.worker_tenant'),
            ]);
        }

        $holidayDate = Carbon::parse($holidayDate)->toDateString();
        $replacementDate = $replacementDate !== null && trim($replacementDate) !== ''
            ? Carbon::parse($replacementDate)->toDateString()
            : null;

        $knownHoliday = BelgianPublicHoliday::query()->whereDate('date', $holidayDate)->exists();
        if (! $knownHoliday) {
            throw ValidationException::withMessages([
                'holiday_date' => __('time.schedule.month_norm.errors.agreement_holiday'),
            ]);
        }

        if ($effect === HolidayAgreementEffect::Replaced) {
            if ($replacementDate === null) {
                throw ValidationException::withMessages([
                    'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_required'),
                ]);
            }
            if ($replacementDate === $holidayDate) {
                throw ValidationException::withMessages([
                    'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_same'),
                ]);
            }
            $year = (int) Carbon::parse($replacementDate)->year;
            $covered = BelgianPublicHoliday::query()->where('year', $year)->count()
                === count(BelgianPublicHolidayCalendar::CODES);
            if (! $covered) {
                throw ValidationException::withMessages([
                    'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_uncovered'),
                ]);
            }
            if (BelgianPublicHoliday::query()->whereDate('date', $replacementDate)->exists()) {
                throw ValidationException::withMessages([
                    'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_holiday'),
                ]);
            }
            $taken = WorkerHolidayAgreement::query()
                ->where('worker_id', $worker->id)
                ->where('holiday_date', '!=', $holidayDate)
                ->whereDate('replacement_date', $replacementDate)
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages([
                    'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_taken'),
                ]);
            }
        } elseif ($replacementDate !== null) {
            throw ValidationException::withMessages([
                'replacement_date' => __('time.schedule.month_norm.errors.agreement_replacement_forbidden'),
            ]);
        }

        $agreement = WorkerHolidayAgreement::query()->updateOrCreate(
            [
                'worker_id' => $worker->id,
                'holiday_date' => $holidayDate,
            ],
            [
                'tenant_id' => $tenantId,
                'effect' => $effect,
                'replacement_date' => $replacementDate,
            ],
        );

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'worker.holiday_agreement_saved',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [
                'holiday_date' => $holidayDate,
                'effect' => $effect->value,
                'replacement_date' => $replacementDate,
            ],
        );

        return $agreement;
    }
}
