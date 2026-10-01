<?php

declare(strict_types=1);

namespace App\Data\Customers;

/**
 * Statistiek voor één klant over een periode: bezoeken, aanwezigheidsduur
 * en de uitsplitsing per werkadres. `visitedLocations()` telt enkel
 * werkadressen met minstens één overlappend bezoek in de periode.
 */
final class CustomerWorkStats
{
    /**
     * @param  array<int, CustomerWorkLocationStats>  $locations  keyed by location id
     */
    public function __construct(
        public int $customerId,
        public int $visits = 0,
        public int $minutes = 0,
        public array $locations = [],
    ) {}

    public function visitedLocations(): int
    {
        return count($this->locations);
    }
}
