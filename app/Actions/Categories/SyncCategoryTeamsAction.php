<?php

namespace App\Actions\Categories;

use App\Contracts\WebhookEvent;
use App\Data\Categories\SyncCategoryTeamsData;
use App\Events\Categories\CategoryTeamsSynced;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Auth;

class SyncCategoryTeamsAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {
    }

    public function handle(Category $category, SyncCategoryTeamsData $data, User $actor): void
    {
        $actorId = $actor->id ?? Auth::id();

        // Filter team IDs by tenant to prevent cross-tenant assignment
        $validTeamIds = InternalTeam::where('tenant_id', $category->tenant_id)
            ->whereIn('id', $data->team_ids)
            ->pluck('id')
            ->toArray();

        $category->teams()->sync($validTeamIds);

        // Audit logging
        $this->audit->record(
            userId: $actorId,
            tenantId: $category->tenant_id,
            action: 'category_teams_synced',
            modelType: 'Category',
            modelId: $category->id,
            payload: [
                'category_name' => $category->name,
                'team_ids' => $validTeamIds,
            ],
        );

        // Dispatch event
        CategoryTeamsSynced::dispatch($category, $validTeamIds, $actorId);
    }
}
