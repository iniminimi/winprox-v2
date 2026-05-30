<?php

namespace App\Actions\Locations;

use App\Models\Unit;
use App\Support\Units\UnitDeletionGuard;
use InvalidArgumentException;

class DeleteUnitAction
{
    public function handle(Unit $unit): void
    {
        $reason = UnitDeletionGuard::blockReason($unit);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        $unit->delete();
    }
}
