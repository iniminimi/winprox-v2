<?php

namespace App\Actions\Communication;

use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;

class BackfillInternalTeamTranslationSlotsAction
{
    public function __construct(private EnsureInternalTeamTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{teams: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $processed = 0;
        $slotsCreated = 0;

        InternalTeam::query()
            ->where('is_active', true)
            ->where('name', '!=', '')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($teams) use (&$processed, &$slotsCreated): void {
                foreach ($teams as $team) {
                    $before = InternalTeamTranslation::query()
                        ->where('internal_team_id', $team->id)
                        ->count();

                    $this->ensureSlots->handle($team);

                    $after = InternalTeamTranslation::query()
                        ->where('internal_team_id', $team->id)
                        ->count();

                    $processed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'teams' => $processed,
            'slots_created' => $slotsCreated,
        ];
    }
}
