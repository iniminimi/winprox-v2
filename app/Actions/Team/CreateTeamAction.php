<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Support\Tenancy;

/**
 * Maakt een operationeel team aan (incl. auto-gegenereerde team-QR-token).
 */
class CreateTeamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): InternalTeam
    {
        return InternalTeam::create([
            'tenant_id' => Tenancy::id(),
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }
}
