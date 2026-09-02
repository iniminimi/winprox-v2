<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Locations\DeleteCategoryAction;
use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Team\DeleteTeamAction;
use App\Data\Categories\SyncCategoryTeamsData;
use App\Models\Category;
use App\Models\EsgMeasurement;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTenantStarterPackAction
{
    public function __construct(
        private DeleteUnitAction $deleteUnit,
        private DeleteLocationAction $deleteLocation,
        private DeleteCategoryAction $deleteCategory,
        private DeleteTeamAction $deleteTeam,
        private SyncCategoryTeamsAction $syncCategoryTeams,
        private AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, User $actor): void
    {
        $payload = $tenant->starter_pack_payload;

        $locale = LocaleSupport::normalize($actor->locale);

        if (! filled($tenant->starter_pack_key) || ! is_array($payload)) {
            throw ValidationException::withMessages([
                'removeStarterPack' => [trans('dashboard.starter_pack.errors.missing', [], $locale)],
            ]);
        }

        $unitIds = array_values(array_map('intval', $payload['unit_ids'] ?? []));
        $locationId = (int) ($payload['location_id'] ?? 0);
        $categoryIds = array_values(array_map('intval', $payload['category_ids'] ?? []));
        $teamIds = array_values(array_map('intval', $payload['team_ids'] ?? []));

        $blocked = ($unitIds !== [] && (
            Unit::query()->where('tenant_id', $tenant->id)->whereIn('id', $unitIds)->whereHas('issues')->exists()
            || EsgMeasurement::query()->whereIn('unit_id', $unitIds)->exists()
        ))
            || ($locationId > 0 && Location::query()->where('tenant_id', $tenant->id)->whereKey($locationId)->whereHas('issues')->exists())
            || ($teamIds !== [] && InternalTeam::query()->where('tenant_id', $tenant->id)->whereIn('id', $teamIds)->whereHas('workers')->exists());

        if ($blocked) {
            throw ValidationException::withMessages([
                'removeStarterPack' => [trans('dashboard.starter_pack.errors.has_issues', [], $locale)],
            ]);
        }

        $units = Unit::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $unitIds)
            ->get();

        $location = $locationId > 0
            ? Location::query()->where('tenant_id', $tenant->id)->whereKey($locationId)->first()
            : null;

        $teams = InternalTeam::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $teamIds)
            ->get();

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
                $this->syncCategoryTeams->handle(
                    $category,
                    new SyncCategoryTeamsData(team_ids: []),
                    $actor,
                );
                $this->deleteCategory->handle($category, $actorId);
            }

            foreach ($teams as $team) {
                $this->deleteTeam->handle($team, $actorId);
            }

            $tenant->forceFill([
                'starter_pack_key' => null,
                'starter_pack_applied_at' => null,
                'starter_pack_payload' => null,
                'starter_pack_result_dismissed_at' => null,
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
