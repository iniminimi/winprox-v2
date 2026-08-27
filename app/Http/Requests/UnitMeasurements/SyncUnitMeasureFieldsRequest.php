<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitMeasurements;

use App\Models\UnitMeasureField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SyncUnitMeasureFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::staticRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function staticRules(): array
    {
        return [
            'measure_field_ids' => ['nullable', 'array'],
            'measure_field_ids.*' => ['integer', 'exists:unit_measure_fields,id'],
        ];
    }

    /**
     * @param  list<int>  $fieldIds
     * @return list<int>
     */
    public static function assertActiveFieldIdsForTenant(int $tenantId, array $fieldIds): array
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $fieldIds)));

        if ($uniqueIds === []) {
            return [];
        }

        $count = UnitMeasureField::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'measure_field_ids' => [__('unit_measurements.errors.fields_invalid')],
            ]);
        }

        return $uniqueIds;
    }
}
