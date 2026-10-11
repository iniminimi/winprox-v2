<?php

namespace App\Support\Time;

use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use Carbon\Carbon;

/**
 * Centrale vergrendelingsregel voor historische roosterblokken.
 *
 * Basisregel: work_date < vandaag (app-tijdzone) = vergrendeld. Gewone
 * schrijfpaden mogen zo'n blok niet aanmaken, wijzigen of verwijderen.
 * Uitzonderingen lopen enkel via de afwezigheidsworkflow (Apply/Restore)
 * en CorrectPlannedShiftAction — deze klasse is bewust niet daar aangeroepen.
 *
 * Uitbreidbaar: isLocked() is het enige oordeelspunt; nieuwe voorwaarden
 * (bv. blok met prikken) haken hier later in zonder dat callers wijzigen.
 */
final class RosterHistoryLock
{
    public static function today(): string
    {
        return Carbon::now(config('app.timezone'))->toDateString();
    }

    public static function isLockedDate(string $date): bool
    {
        return $date < self::today();
    }

    public static function isLocked(PlannedShift $shift): bool
    {
        return self::isLockedDate($shift->work_date->toDateString());
    }

    public static function assertWritableDate(string $date): void
    {
        if (self::isLockedDate($date)) {
            throw new RosterValidationException('time.schedule.errors.locked_day');
        }
    }

    public static function assertWritable(PlannedShift $shift): void
    {
        self::assertWritableDate($shift->work_date->toDateString());
    }
}
