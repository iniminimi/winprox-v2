<?php

declare(strict_types=1);

namespace App\Support\Locations;

use App\Models\EsgMeasurement;
use App\Models\IssueRoundStop;
use App\Models\Location;
use App\Models\Unit;
use App\Models\WorkVisit;
use App\Support\Units\UnitDeletionGuard;

/**
 * Eén bron voor "mag deze locatie weg" — gebruikt door DeleteLocationAction en
 * de import-undo's (locaties én klanten). Locaties met enkel onaangeroerde
 * site-units ("Hele locatie") mogen mee gewist worden; een werkadres met
 * WorkVisits nooit (CIAO-bewijs, work_visits cascadet mee).
 */
final class LocationDeletionGuard
{
    public const BLOCK_HAS_UNITS = 'location_has_units';

    public const BLOCK_HAS_ISSUES = 'location_has_issues';

    public const BLOCK_HAS_CONTENT = 'location_has_content';

    public const BLOCK_HAS_ESG_MEASUREMENTS = 'location_has_esg_measurements';

    public const BLOCK_HAS_WORK_VISITS = 'location_has_work_visits';

    public static function canDelete(Location $location): bool
    {
        return self::blockReason($location) === null;
    }

    public static function blockReason(Location $location): ?string
    {
        if (! self::hasOnlyPristineSiteUnits($location)) {
            return self::BLOCK_HAS_UNITS;
        }

        if ($location->issues()->exists()) {
            return self::BLOCK_HAS_ISSUES;
        }

        if ($location->documents()->exists()
            || $location->announcements()->exists()
            || $location->bulkBatches()->exists()) {
            return self::BLOCK_HAS_CONTENT;
        }

        if (EsgMeasurement::query()->where('location_id', $location->id)->exists()) {
            return self::BLOCK_HAS_ESG_MEASUREMENTS;
        }

        if (WorkVisit::query()->where('location_id', $location->id)->exists()) {
            return self::BLOCK_HAS_WORK_VISITS;
        }

        return null;
    }

    /**
     * Site-unit die nog onaangeroerd is: geen meldingen/taken, rondestops,
     * reserveringen, documenten, mededelingen, ESG-metingen of werkbezoeken.
     */
    public static function isPristineSiteUnit(Unit $unit): bool
    {
        return (bool) $unit->is_site_unit
            && UnitDeletionGuard::canDelete($unit)
            && ! IssueRoundStop::query()->where('unit_id', $unit->id)->exists()
            && ! $unit->reservations()->exists()
            && ! $unit->documents()->exists()
            && ! $unit->announcements()->exists()
            && ! WorkVisit::query()->where('unit_id', $unit->id)->exists();
    }

    private static function hasOnlyPristineSiteUnits(Location $location): bool
    {
        return $location->units()->get()->every(
            static fn (Unit $unit): bool => self::isPristineSiteUnit($unit),
        );
    }
}
