<?php

namespace App\Events\Units;

use App\Models\Unit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnitTeamsSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Unit $unit,
        public array $teamIds,
        public int $actorId,
    ) {
    }
}
