<?php

namespace App\Http\Requests\Locations;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $locationId = null, ?int $ignoreUnitId = null, ?int $tenantId = null): array
    {
        $unique = Rule::unique('units', 'name')
            ->where(fn ($q) => $q->where('location_id', $locationId));

        if ($ignoreUnitId !== null) {
            $unique->ignore($ignoreUnitId);
        }

        $categoryRules = ['nullable'];
        if (Schema::hasTable('categories')) {
            $categoryExists = Rule::exists('categories', 'id');
            if ($tenantId !== null) {
                $categoryExists = $categoryExists->where(fn ($q) => $q->where('tenant_id', $tenantId));
            }

            $categoryRules = ['nullable', 'integer', $categoryExists];
        }

        return [
            'name' => ['required', 'string', 'min:1', 'max:255', $unique],
            'description' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
            'category_id' => $categoryRules,
            'public_reports_enabled' => ['boolean'],
            'allow_reservations' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $locationId = $this->route('location')?->id ?? $this->input('location_id');
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet(
            $locationId ? (int) $locationId : null,
            null,
            $tenantId ? (int) $tenantId : null,
        );
    }
}
