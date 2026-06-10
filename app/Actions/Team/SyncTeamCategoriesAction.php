<?php

namespace App\Actions\Team;

use App\Models\Category;
use App\Models\InternalTeam;
use App\Support\Audit\AuditRecorder;

class SyncTeamCategoriesAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
    ) {
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    public function handle(InternalTeam $team, array $categoryIds, ?int $actorUserId = null): void
    {
        // Filter category IDs by tenant to prevent cross-tenant assignment
        $validCategoryIds = Category::where('tenant_id', $team->tenant_id)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->toArray();

        $team->categories()->sync($validCategoryIds);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $team->tenant_id,
            action: 'team_categories_synced',
            modelType: InternalTeam::class,
            modelId: $team->id,
            payload: [
                'team_name' => $team->name,
                'category_ids' => $validCategoryIds,
            ],
        );
    }
}
