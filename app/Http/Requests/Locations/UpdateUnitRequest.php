<?php

namespace App\Http\Requests\Locations;

class UpdateUnitRequest extends StoreUnitRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSetFor(?int $locationId, ?int $ignoreUnitId = null): array
    {
        return self::ruleSet($locationId, $ignoreUnitId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $location = $this->route('location');
        $unit = $this->route('unit');

        return self::ruleSet(
            $location?->id ? (int) $location->id : null,
            $unit?->id ? (int) $unit->id : null,
        );
    }
}
