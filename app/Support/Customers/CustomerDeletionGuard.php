<?php

declare(strict_types=1);

namespace App\Support\Customers;

use App\Models\Customer;
use App\Models\Location;
use App\Support\Locations\LocationDeletionGuard;

/**
 * Klant mag weg zolang alle gekoppelde locaties weg mogen
 * (lege site / alleen onaangeroerde «Hele locatie»-unit) — of er geen locaties zijn.
 */
final class CustomerDeletionGuard
{
    public static function canDelete(Customer $customer): bool
    {
        $locations = $customer->relationLoaded('locations')
            ? $customer->locations
            : $customer->locations()->get();

        return $locations->every(
            static fn (Location $location): bool => LocationDeletionGuard::canDelete($location),
        );
    }
}
