<?php

namespace App\Actions\Categories;

use App\Data\Categories\SyncCategoryTeamsData;
use App\Events\Categories\CategoryTeamsSynced;
use App\Models\Category;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

readonly class SyncCategoryTeamsAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {
    }

    /**
     * @return array<int, int> The synced team IDs
     */
    public function handle(
        Category $category,
        SyncCategoryTeamsData $data,
        ?User $actor = null,
    ): array {
        $teamIds = [];

        DB::transaction(function () use ($category, $data, $actor, &$teamIds) {
            $syncData = [];
            $primaryTeamId = null;

            // First pass: find the first team marked as primary
            foreach ($data->teams as $team) {
                if ($team['is_primary'] && $primaryTeamId === null) {
                    $primaryTeamId = $team['id'];
                }
                $teamIds[] = $team['id'];
            }

            // Second pass: build sync data with correct is_primary values
            foreach ($data->teams as $team) {
                $syncData[$team['id']] = [
                    'is_primary' => $team['id'] === $primaryTeamId,
                ];
            }

            // Sync the relationships
            $category->teams()->sync($syncData);

            // Log audit
            $this->audit->record(
                userId: $actor?->id,
                tenantId: $category->tenant_id,
                action: 'category_teams_synced',
                modelType: Category::class,
                modelId: $category->id,
                payload: [
                    'team_ids' => $teamIds,
                    'primary_team_id' => $primaryTeamId,
                ],
            );

            // Dispatch event
            CategoryTeamsSynced::dispatch(
                category: $category,
                teamIds: $teamIds,
                actor: $actor,
            );
        });

        return $teamIds;
    }
}
