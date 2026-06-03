<?php

namespace App\Http\Requests\Locations;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSetFor(?int $tenantId = null, ?int $ignoreCategoryId = null): array
    {
        return self::ruleSet($tenantId, $ignoreCategoryId);
    }
}
