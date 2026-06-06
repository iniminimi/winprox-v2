<?php

namespace App\Actions\Units;

use App\Data\Units\SyncUnitTeamsData;
use App\Events\Units\UnitTeamsSynced;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Auth;

class SyncUnitTeamsAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, SyncUnitTeamsData $data, $actor = null): void
    {
        $actorId = $actor instanceof \App\Models\User ? $actor->id : ($actor ?? Auth::id());

        // Filter teams to only include teams from the same tenant
        $validTeamIds = \App\Models\InternalTeam::where('tenant_id', $unit->tenant_id)
            ->whereIn('id', $data->teams)
            ->pluck('id')
            ->toArray();

        $syncData = collect($validTeamIds)
            ->mapWithKeys(fn ($team) => [$team => []])
            ->toArray();

        $unit->teams()->sync($syncData);

        // Audit logging
        $this->audit->record(
            userId: $actorId,
            tenantId: $unit->tenant_id,
            action: 'unit_teams_synced',
            modelType: 'Unit',
            modelId: $unit->id,
            payload: [
                'unit_name' => $unit->name,
                'team_ids' => $validTeamIds,
            ],
        );

        // Dispatch domain event
        UnitTeamsSynced::dispatch($unit, $validTeamIds, $actorId);
    }
}
