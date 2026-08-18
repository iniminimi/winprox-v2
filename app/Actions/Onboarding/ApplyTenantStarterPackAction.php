<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Categories\SyncCategoryTeamsAction;
use App\Actions\Locations\CreateCategoryAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\CreateUnitAction;
use App\Actions\Locations\DeleteLocationAction;
use App\Actions\Team\CreateTeamAction;
use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Data\Categories\SyncCategoryTeamsData;
use App\Data\Onboarding\ApplyTenantStarterPackData;
use App\Enums\CategoryTranslationStatus;
use App\Enums\InternalTeamTranslationStatus;
use App\Enums\LocationTranslationStatus;
use App\Enums\UnitTranslationStatus;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\InternalTeam;
use App\Models\InternalTeamTranslation;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Onboarding\TenantStarterPackCatalog;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ApplyTenantStarterPackAction
{
    public function __construct(
        private CreateTeamAction $createTeam,
        private CreateCategoryAction $createCategory,
        private CreateLocationAction $createLocation,
        private CreateUnitAction $createUnit,
        private DeleteLocationAction $deleteLocation,
        private SyncCategoryTeamsAction $syncCategoryTeams,
        private EnsureDefaultClockPointAction $ensureDefaultClockPoint,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     team_ids: list<int>,
     *     category_ids: list<int>,
     *     location_id: int,
     *     unit_ids: list<int>
     * }
     */
    public function handle(Tenant $tenant, ApplyTenantStarterPackData $data, User $actor): array
    {
        $locale = LocaleSupport::normalize($data->locale);
        $this->assertEligible($tenant, $locale);
        $definition = TenantStarterPackCatalog::definition($data->type);

        $payload = DB::transaction(function () use ($tenant, $data, $actor, $locale, $definition): array {
            $tenantId = (int) $tenant->id;
            $actorId = (int) $actor->id;

            $this->removeEmptyLeftoverLocations($tenant, $actorId, $locale);

            $teamsByKey = [];
            $sort = 0;
            foreach (array_keys($definition['teams']) as $teamKey) {
                $names = TenantStarterPackCatalog::namesByLocale(
                    TenantStarterPackCatalog::teamNameKey($data->type, (string) $teamKey)
                );
                $team = $this->createTeam->handle([
                    'name' => $names[$locale],
                    'original_language' => $locale,
                    'sort_order' => $sort,
                    'is_active' => true,
                ], $tenantId, $actorId);
                $this->fillTeamTranslations($team, $locale, $names);
                $teamsByKey[(string) $teamKey] = $team;
                $sort++;
            }

            $categoriesByKey = [];
            foreach ($definition['categories'] as $categoryKey) {
                $names = TenantStarterPackCatalog::namesByLocale(
                    TenantStarterPackCatalog::categoryNameKey($data->type, (string) $categoryKey)
                );
                $category = $this->createCategory->handle($tenantId, [
                    'name' => $names[$locale],
                    'original_language' => $locale,
                ], $actorId);
                $this->fillCategoryTranslations($category, $locale, $names);
                $categoriesByKey[(string) $categoryKey] = $category;
            }

            foreach ($categoriesByKey as $categoryKey => $category) {
                $teamIds = [];
                foreach ($definition['teams'] as $teamKey => $teamDef) {
                    if (in_array($categoryKey, $teamDef['categories'], true)) {
                        $teamIds[] = (int) $teamsByKey[(string) $teamKey]->id;
                    }
                }
                $this->syncCategoryTeams->handle(
                    $category,
                    new SyncCategoryTeamsData(team_ids: $teamIds),
                    $actor,
                );
            }

            $locationNames = TenantStarterPackCatalog::namesByLocale(
                TenantStarterPackCatalog::locationNameKey($data->type)
            );
            $location = $this->createLocation->handle([
                'name' => $locationNames[$locale],
                'original_language' => $locale,
                'street' => $tenant->street,
                'house_number' => $tenant->house_number,
                'postal_code' => $tenant->postal_code,
                'city' => $tenant->city,
                'country_code' => $tenant->country_code,
            ], $tenantId, $actorId);
            $this->fillLocationTranslations($location, $locale, $locationNames);

            $unitIds = [];
            foreach ($definition['units'] as $unitDef) {
                $unitNames = TenantStarterPackCatalog::namesByLocale(
                    TenantStarterPackCatalog::unitNameKey($data->type, (string) $unitDef['key'])
                );
                $category = $categoriesByKey[(string) $unitDef['category']];
                $unit = $this->createUnit->handle($location, [
                    'name' => $unitNames[$locale],
                    'original_language' => $locale,
                    'category_id' => $category->id,
                    'public_reports_enabled' => true,
                ], $tenantId, $actorId);
                $this->fillUnitTranslations($unit, $locale, $unitNames);
                $unitIds[] = (int) $unit->id;
            }

            $this->ensureDefaultClockPoint->handle(
                $tenant,
                trans('team.clock_point_qr.default_name', [], $locale),
                $actorId,
            );

            $payload = [
                'type' => $data->type->value,
                'locale' => $locale,
                'team_ids' => array_values(array_map(
                    fn (InternalTeam $team): int => (int) $team->id,
                    $teamsByKey,
                )),
                'category_ids' => array_values(array_map(
                    fn (Category $category): int => (int) $category->id,
                    $categoriesByKey,
                )),
                'location_id' => (int) $location->id,
                'unit_ids' => $unitIds,
            ];

            $tenant->forceFill([
                'starter_pack_key' => $data->type->value,
                'starter_pack_applied_at' => now(),
                'starter_pack_payload' => $payload,
            ])->save();

            return $payload;
        });

        $this->audit->record(
            userId: (int) $actor->id,
            tenantId: (int) $tenant->id,
            action: 'starter_pack.applied',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: $payload,
        );

        return $payload;
    }

    private function assertEligible(Tenant $tenant, string $locale): void
    {
        if (filled($tenant->starter_pack_key)) {
            throw ValidationException::withMessages([
                'starterPackType' => [trans('dashboard.starter_pack.errors.already_applied', [], $locale)],
            ]);
        }

        $hasData = InternalTeam::query()->where('tenant_id', $tenant->id)->exists()
            || Category::query()->where('tenant_id', $tenant->id)->exists()
            || Unit::query()->where('tenant_id', $tenant->id)->exists();

        if ($hasData) {
            throw ValidationException::withMessages([
                'starterPackType' => [trans('dashboard.starter_pack.errors.not_empty', [], $locale)],
            ]);
        }
    }

    private function removeEmptyLeftoverLocations(Tenant $tenant, int $actorId, string $locale): void
    {
        $locations = Location::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        foreach ($locations as $location) {
            try {
                $this->deleteLocation->handle($location, $actorId);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'starterPackType' => [trans('dashboard.starter_pack.errors.not_empty', [], $locale)],
                ]);
            }
        }
    }

    /**
     * @param  array<string, string>  $namesByLocale
     */
    private function fillTeamTranslations(InternalTeam $team, string $sourceLocale, array $namesByLocale): void
    {
        foreach ($namesByLocale as $locale => $name) {
            if ($locale === $sourceLocale) {
                continue;
            }
            InternalTeamTranslation::query()
                ->where('internal_team_id', $team->id)
                ->where('locale', $locale)
                ->update([
                    'name' => $name,
                    'status' => InternalTeamTranslationStatus::Completed,
                ]);
        }
    }

    /**
     * @param  array<string, string>  $namesByLocale
     */
    private function fillCategoryTranslations(Category $category, string $sourceLocale, array $namesByLocale): void
    {
        foreach ($namesByLocale as $locale => $name) {
            if ($locale === $sourceLocale) {
                continue;
            }
            CategoryTranslation::query()
                ->where('category_id', $category->id)
                ->where('locale', $locale)
                ->update([
                    'name' => $name,
                    'status' => CategoryTranslationStatus::Completed,
                ]);
        }
    }

    /**
     * @param  array<string, string>  $namesByLocale
     */
    private function fillLocationTranslations(Location $location, string $sourceLocale, array $namesByLocale): void
    {
        foreach ($namesByLocale as $locale => $name) {
            if ($locale === $sourceLocale) {
                continue;
            }
            LocationTranslation::query()
                ->where('location_id', $location->id)
                ->where('locale', $locale)
                ->update([
                    'name' => $name,
                    'status' => LocationTranslationStatus::Completed,
                ]);
        }
    }

    /**
     * @param  array<string, string>  $namesByLocale
     */
    private function fillUnitTranslations(Unit $unit, string $sourceLocale, array $namesByLocale): void
    {
        foreach ($namesByLocale as $locale => $name) {
            if ($locale === $sourceLocale) {
                continue;
            }
            UnitTranslation::query()
                ->where('unit_id', $unit->id)
                ->where('locale', $locale)
                ->update([
                    'name' => $name,
                    'status' => UnitTranslationStatus::Completed,
                ]);
        }
    }
}
