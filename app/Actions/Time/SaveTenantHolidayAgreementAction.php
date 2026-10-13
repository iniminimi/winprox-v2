<?php

namespace App\Actions\Time;

use App\Enums\HolidayAgreementEffect;
use App\Models\BelgianPublicHoliday;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\BelgianPublicHolidayCalendar;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Bewaart één bedrijfsbrede feestdagafspraak. Conflicten met andere afspraken
 * van dezelfde tenant worden geweigerd. Een datum buiten de kalenderdekking
 * is geen geldige afspraak.
 */
class SaveTenantHolidayAgreementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Tenant $tenant,
        string $holidayDate,
        HolidayAgreementEffect $effect,
        ?string $replacementDate,
        ?int $actorUserId = null,
    ): TenantHolidayAgreement {
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
            $taken = TenantHolidayAgreement::query()
                ->where('tenant_id', $tenant->id)
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

        $agreement = TenantHolidayAgreement::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'holiday_date' => $holidayDate,
            ],
            [
                'effect' => $effect,
                'replacement_date' => $replacementDate,
            ],
        );

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.holiday_agreement_saved',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: [
                'holiday_date' => $holidayDate,
                'effect' => $effect->value,
                'replacement_date' => $replacementDate,
            ],
        );

        return $agreement;
    }
}
