<?php

declare(strict_types=1);

namespace App\Http\Requests\Esg;

use App\Data\Esg\RecordEsgMeasurementData;
use App\Models\EsgIndicator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordEsgMeasurementRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet($tenantId ? (int) $tenantId : null);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null): array
    {
        $tenantScope = fn ($query) => $query;
        if ($tenantId !== null) {
            $tenantScope = fn ($query) => $query->where('tenant_id', $tenantId);
        }

        return [
            'task_id' => [
                'required',
                'integer',
                Rule::exists('tasks', 'id')->where($tenantScope),
            ],
            'esg_indicator_id' => [
                'required',
                'integer',
                Rule::exists('esg_indicators', 'id')->where(function ($query) use ($tenantId): void {
                    if ($tenantId !== null) {
                        $query->where('tenant_id', $tenantId);
                    }
                    $query->where('is_active', true);
                }),
            ],
            'recorded_at' => ['required', 'date'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_boolean' => ['nullable', 'boolean'],
            'value_string' => ['nullable', 'string', 'max:500'],
            'value_json' => ['nullable', 'array'],
            'corrects_measurement_id' => [
                'nullable',
                'integer',
                Rule::exists('esg_measurements', 'id')->where($tenantScope),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertValueMatchesIndicator(array $validated, EsgIndicator $indicator): void
    {
        $type = $indicator->type;
        $expectedColumn = $type->valueColumn();
        $valueColumns = [
            'value_numeric',
            'value_boolean',
            'value_string',
            'value_json',
        ];

        $providedColumns = [];
        foreach ($valueColumns as $column) {
            if (self::valueIsPresent($validated, $column)) {
                $providedColumns[] = $column;
            }
        }

        if ($providedColumns === []) {
            throw ValidationException::withMessages([
                $expectedColumn => [__('esg.errors.measurement_value_required')],
            ]);
        }

        if (count($providedColumns) > 1) {
            throw ValidationException::withMessages([
                $expectedColumn => [__('esg.errors.measurement_value_multiple')],
            ]);
        }

        if ($providedColumns[0] !== $expectedColumn) {
            throw ValidationException::withMessages([
                $providedColumns[0] => [__('esg.errors.measurement_value_wrong_type')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function toData(array $validated, EsgIndicator $indicator): RecordEsgMeasurementData
    {
        self::assertValueMatchesIndicator($validated, $indicator);

        return RecordEsgMeasurementData::fromValidated($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private static function valueIsPresent(array $validated, string $key): bool
    {
        if (! array_key_exists($key, $validated)) {
            return false;
        }

        $value = $validated[$key];

        if ($key === 'value_boolean' || $key === 'value_json' || $key === 'value_numeric') {
            return $value !== null;
        }

        return $value !== null && $value !== '';
    }
}
