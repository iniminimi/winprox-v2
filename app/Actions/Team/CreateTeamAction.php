<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;

/**
 * Maakt een operationeel team aan (incl. auto-gegenereerde team-QR-token).
 *
 * Integration-first (§3.0): tenant wordt expliciet meegegeven, niet via globale
 * state — identiek aanroepbaar door Livewire, API, CLI, job.
 */
class CreateTeamAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId): InternalTeam
    {
        return InternalTeam::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }
}
