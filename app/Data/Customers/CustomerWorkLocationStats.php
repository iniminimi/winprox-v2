<?php

declare(strict_types=1);

namespace App\Data\Customers;

/**
 * Statistiek voor één werkadres binnen een klant, over een periode.
 */
final class CustomerWorkLocationStats
{
    public function __construct(
        public int $locationId,
        public int $visits = 0,
        public int $minutes = 0,
    ) {}
}
