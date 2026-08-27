<?php

declare(strict_types=1);

namespace App\Data\Reservations;

final readonly class ExportReservationsFilterData
{
    public function __construct(
        public string $status = 'upcoming',
        public ?int $locationId = null,
    ) {}
}
