<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Models\Category;
use App\Models\Unit;
use App\Support\Units\UnitCategoryPortalInheritance;

class SyncCategoryPortalFlagsToInheritingUnitsAction
{
    /**
     * @param  array{
     *     allow_reservations: bool,
     *     allow_unit_checks: bool,
     *     allow_unit_measurements: bool,
     *     require_reporter_contact: bool,
     *     require_reporter_email_verification: bool,
     * }  $previousDefaults
     */
    public function handle(Category $category, array $previousDefaults, ?int $actorUserId = null): int
    {
        $newDefaults = UnitCategoryPortalInheritance::defaultsFromCategory($category);

        if ($previousDefaults === $newDefaults) {
            return 0;
        }

        $updatedCount = 0;

        Unit::query()
            ->where('tenant_id', (int) $category->tenant_id)
            ->where('category_id', (int) $category->id)
            ->orderBy('id')
            ->each(function (Unit $unit) use ($previousDefaults, $newDefaults, &$updatedCount): void {
                if (! UnitCategoryPortalInheritance::unitMatchesDefaults($unit, $previousDefaults)) {
                    return;
                }

                $unit->update($newDefaults);
                $updatedCount++;
            });

        return $updatedCount;
    }
}
