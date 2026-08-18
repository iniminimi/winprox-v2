<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Team\DeleteTeamAction;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Units\UnitDeletionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTenantStarterPackAction
{
    public function __construct(
        private DeleteUnitAction $deleteUnit,
        private DeleteLocationAction $deleteLocation,
        private DeleteCategoryAction $deleteCategory,
        private DeleteTeamAction $deleteTeam,
        private AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, User $actor): void
    {
        $payload = $tenant->starter_pack_payload;

        if (! filled($tenant->starter_pack_key) || ! is_array($payload)) {
            throw ValidationException::withMessages([
                'removeStarterPack' => [__('dashboard.starter_pack.errors.missing')],
            ]);
        }

        $unitIds = array_values(array_map('intval', $payload['unit_ids'] ?? []));
        $locationId = (int) ($payload['location_id'] ?? 0);
        $categoryIds = array_values(array_map('intval', $payload['category_ids'] ?? []));
        $teamIds = array_values(array_map('intval', $payload['team_ids'] ?? []));

        $units = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $unitIds)
            ->get();

        foreach ($units as $unit) {
            if (UnitDeletionGuard::blockReason($unit) !== null) {
                throw ValidationException::withMessages([
                    'removeStarterPack' => [__('dashboard.starter_pack.errors.has_issues')],
                ]);
            }
        }

        $location = $locationId > 0
            ? Location::query()->where('tenant_id', $tenant->id)->whereKey($locationId)->first()
            : null;

        if ($location !== null && $location->issues()->exists()) {
            throw ValidationException::withMessages([
                'removeStarterPack' => [__('dashboard.starter_pack.errors.has_issues')],
            ]);
        }

        $teams = InternalTeam::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $teamIds)
            ->get();

        foreach ($teams as $team) {
            if ($team->workers()->exists()) {
                throw ValidationException::withMessages([
                    'removeStarterPack' => [__('dashboard.starter_pack.errors.has_issues')],
                ]);
            }
        }

        DB::transaction(function () use ($tenant, $actor, $units, $location, $categoryIds, $teams, $payload): void {
            $actorId = (int) $actor->id;

            foreach ($units as $unit) {
                $this->deleteUnit->handle($unit, $actorId);
            }

            if ($location !== null && $location->units()->doesntExist()) {
                $this->deleteLocation->handle($location, $actorId);
            }

            $categories = Category::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $categoryIds)
                ->get();

            foreach ($categories as $category) {
                $category->teams()->detach();
                $this->deleteCategory->handle($category, $actorId);
            }

            foreach ($teams as $team) {
                $this->deleteTeam->handle($team, $actorId);
            }

            $tenant->forceFill([
                'starter_pack_key' => null,
                'starter_pack_applied_at' => null,
                'starter_pack_payload' => null,
            ])->save();

            $this->audit->record(
                userId: $actorId,
                tenantId: (int) $tenant->id,
                action: 'starter_pack.removed',
                modelType: Tenant::class,
                modelId: (int) $tenant->id,
                payload: ['type' => $payload['type'] ?? null],
            );
        });
    }
}
