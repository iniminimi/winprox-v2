<?php

namespace App\Actions\Teams;

use App\Data\Teams\SyncTeamCategoriesData;
use App\Events\Teams\TeamCategoriesSynced;
use App\Models\InternalTeam;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

readonly class SyncTeamCategoriesAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {
    }

    /**
     * @return array<int, int> The synced category IDs
     */
    public function handle(
        InternalTeam $team,
        SyncTeamCategoriesData $data,
        ?User $actor = null,
    ): array {
        $categoryIds = [];

        DB::transaction(function () use ($team, $data, $actor, &$categoryIds) {
            $syncData = [];
            $primaryCategoryId = null;

            // First pass: find the first category marked as primary
            foreach ($data->categories as $category) {
                if ($category['is_primary'] && $primaryCategoryId === null) {
                    $primaryCategoryId = $category['id'];
                }
                $categoryIds[] = $category['id'];
            }

            // Second pass: build sync data with correct is_primary values
            foreach ($data->categories as $category) {
                $syncData[$category['id']] = [
                    'is_primary' => $category['id'] === $primaryCategoryId,
                ];
            }

            // Sync the relationships
            $team->categories()->sync($syncData);

            // Log audit
            $this->audit->record(
                userId: $actor?->id,
                tenantId: $team->tenant_id,
                action: 'team_categories_synced',
                modelType: InternalTeam::class,
                modelId: $team->id,
                payload: [
                    'category_ids' => $categoryIds,
                    'primary_category_id' => $primaryCategoryId,
                ],
            );

            // Dispatch event
            TeamCategoriesSynced::dispatch(
                team: $team,
                categoryIds: $categoryIds,
                actor: $actor,
            );
        });

        return $categoryIds;
    }
}
