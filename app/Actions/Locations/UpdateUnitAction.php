<?php

namespace App\Actions\Locations;

use App\Models\Unit;

class UpdateUnitAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Unit $unit, array $data): Unit
    {
        $unit->update([
            'name' => trim((string) $data['name']),
            'default_internal_team_id' => $data['default_internal_team_id'] ?? null,
        ]);

        return $unit->fresh();
    }
}
