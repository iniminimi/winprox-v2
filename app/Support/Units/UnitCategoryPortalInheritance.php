<?php

declare(strict_types=1);

namespace App\Support\Units;

use App\Models\Category;
use App\Models\Unit;

final class UnitCategoryPortalInheritance
{
    /**
     * @return array{
     *     allow_reservations: bool,
     *     allow_unit_checks: bool,
     *     allow_unit_measurements: bool,
     *     require_reporter_contact: bool,
     *     require_reporter_email_verification: bool,
     * }
     */
    public static function defaultsFromCategory(?Category $category): array
    {
        if ($category === null) {
            return self::emptyDefaults();
        }

        return [
            'allow_reservations' => (bool) $category->is_reservable,
            'allow_unit_checks' => (bool) $category->allow_unit_checks,
            'allow_unit_measurements' => (bool) $category->allow_unit_measurements,
            'require_reporter_contact' => (bool) $category->require_reporter_contact,
            'require_reporter_email_verification' => (bool) $category->require_reporter_email_verification,
        ];
    }

    /**
     * @param  array{
     *     allow_reservations: bool,
     *     allow_unit_checks: bool,
     *     allow_unit_measurements: bool,
     *     require_reporter_contact: bool,
     *     require_reporter_email_verification: bool,
     * }  $defaults
     */
    public static function unitMatchesDefaults(Unit $unit, array $defaults): bool
    {
        return (bool) $unit->allow_reservations === (bool) $defaults['allow_reservations']
            && (bool) $unit->allow_unit_checks === (bool) $defaults['allow_unit_checks']
            && (bool) $unit->allow_unit_measurements === (bool) $defaults['allow_unit_measurements']
            && (bool) $unit->require_reporter_contact === (bool) $defaults['require_reporter_contact']
            && (bool) $unit->require_reporter_email_verification === (bool) $defaults['require_reporter_email_verification'];
    }

    /**
     * @param  array{
     *     allow_reservations: bool,
     *     allow_unit_checks: bool,
     *     allow_unit_measurements: bool,
     *     require_reporter_contact: bool,
     *     require_reporter_email_verification: bool,
     * }  $defaults
     */
    public static function livewireFlagsMatchDefaults(
        bool $allowReservations,
        bool $allowUnitChecks,
        bool $allowUnitMeasurements,
        bool $requireReporterContact,
        bool $requireReporterEmailVerification,
        array $defaults,
    ): bool {
        return $allowReservations === (bool) $defaults['allow_reservations']
            && $allowUnitChecks === (bool) $defaults['allow_unit_checks']
            && $allowUnitMeasurements === (bool) $defaults['allow_unit_measurements']
            && $requireReporterContact === (bool) $defaults['require_reporter_contact']
            && $requireReporterEmailVerification === (bool) $defaults['require_reporter_email_verification'];
    }

    /**
     * @return array{
     *     allow_reservations: bool,
     *     allow_unit_checks: bool,
     *     allow_unit_measurements: bool,
     *     require_reporter_contact: bool,
     *     require_reporter_email_verification: bool,
     * }
     */
    private static function emptyDefaults(): array
    {
        return [
            'allow_reservations' => false,
            'allow_unit_checks' => false,
            'allow_unit_measurements' => false,
            'require_reporter_contact' => false,
            'require_reporter_email_verification' => false,
        ];
    }
}
