<?php

declare(strict_types=1);

namespace App\Support\Onboarding;

use App\Enums\TenantStarterPackType;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Carbon;

final readonly class TenantStarterPackSummary
{
    /**
     * @param  list<string>  $teamNames
     * @param  list<string>  $categoryNames
     * @param  list<string>  $unitNames
     * @param  list<array{label: string, enabled: bool}>  $workMenu
     */
    public function __construct(
        public TenantStarterPackType $type,
        public array $teamNames,
        public array $categoryNames,
        public string $locationName,
        public array $unitNames,
        public array $workMenu,
        public ?Carbon $appliedAt,
    ) {}

    public static function for(Tenant $tenant): ?self
    {
        $key = $tenant->starter_pack_key;
        $payload = $tenant->starter_pack_payload;

        if (! is_string($key) || $key === '' || ! is_array($payload)) {
            return null;
        }

        $type = TenantStarterPackType::tryFrom($key);
        if ($type === null) {
            return null;
        }

        $teamIds = array_values(array_map('intval', $payload['team_ids'] ?? []));
        $categoryIds = array_values(array_map('intval', $payload['category_ids'] ?? []));
        $unitIds = array_values(array_map('intval', $payload['unit_ids'] ?? []));
        $locationId = (int) ($payload['location_id'] ?? 0);

        $teamNames = $teamIds === []
            ? []
            : InternalTeam::query()
                ->with('translations')
                ->whereIn('id', $teamIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (InternalTeam $team): string => $team->localizedName())
                ->all();

        $categoryNames = $categoryIds === []
            ? []
            : Category::query()
                ->with('translations')
                ->whereIn('id', $categoryIds)
                ->orderBy('id')
                ->get()
                ->map(fn (Category $category): string => $category->localizedName())
                ->all();

        $location = $locationId > 0
            ? Location::query()->with('translations')->whereKey($locationId)->first()
            : null;

        $unitNames = $unitIds === []
            ? []
            : Unit::query()
                ->with('translations')
                ->whereIn('id', $unitIds)
                ->orderBy('id')
                ->get()
                ->map(fn (Unit $unit): string => $unit->localizedName())
                ->all();

        return new self(
            type: $type,
            teamNames: $teamNames,
            categoryNames: $categoryNames,
            locationName: $location?->localizedName() ?? '',
            unitNames: $unitNames,
            workMenu: TenantStarterPackCatalog::workMenuItemsForTenant($tenant, app()->getLocale()),
            appliedAt: $tenant->starter_pack_applied_at,
        );
    }
}
