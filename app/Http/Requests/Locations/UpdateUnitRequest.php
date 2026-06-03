<?php

namespace App\Http\Requests\Locations;

class UpdateUnitRequest extends StoreUnitRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSetFor(?int $locationId, ?int $ignoreUnitId = null, ?int $tenantId = null): array
    {
        return self::ruleSet($locationId, $ignoreUnitId, $tenantId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $unit = $this->route('unit');
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet(
            $location?->id ? (int) $location->id : null,
            $unit?->id ? (int) $unit->id : null,
            $tenantId ? (int) $tenantId : null,
        );
    }
}
