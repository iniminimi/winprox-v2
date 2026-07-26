<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null, ?int $ignoreCategoryId = null): array
    {
        $unique = Rule::unique('categories', 'name');
        if ($tenantId !== null) {
            $unique = $unique->where(fn ($q) => $q->where('tenant_id', $tenantId));
        }
        if ($ignoreCategoryId !== null) {
            $unique->ignore($ignoreCategoryId);
        }

        return [
            'name' => ['required', 'string', 'min:1', 'max:255', $unique],
            'allow_gps_location' => ['sometimes', 'boolean'],
            'is_reservable' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet($tenantId ? (int) $tenantId : null);
    }
}
