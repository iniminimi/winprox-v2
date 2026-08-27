<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitMeasurements;

use App\Data\UnitMeasurements\SaveUnitMeasureFieldData;
use App\Enums\UnitMeasureFieldType;
use App\Models\UnitMeasureField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveUnitMeasureFieldRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(UnitMeasureFieldType::values())],
            'unit_of_measure' => ['nullable', 'string', 'max:32'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'options' => ['nullable', 'array', 'max:30'],
            'options.*' => ['string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array{
     *     name: string,
     *     type: string,
     *     unit_of_measure?: ?string,
     *     min_value?: float|int|string|null,
     *     max_value?: float|int|string|null,
     *     options?: list<string>|null,
     *     is_active?: bool
     * }  $input
     */
    public static function toData(array $input, int $tenantId, ?UnitMeasureField $field = null): SaveUnitMeasureFieldData
    {
        $type = UnitMeasureFieldType::from((string) $input['type']);
        $name = trim((string) $input['name']);
        $unitOfMeasure = $type->usesUnitOfMeasure()
            ? (filled($input['unit_of_measure'] ?? null) ? trim((string) $input['unit_of_measure']) : null)
            : null;
        $options = $type->usesOptionList()
            ? self::normalizeOptions($input['options'] ?? [])
            : null;

        if ($type->usesOptionList() && ($options === null || $options === [])) {
            throw ValidationException::withMessages([
                'options' => [__('unit_measurements.errors.options_required')],
            ]);
        }

        if ($field !== null && $field->exists && $field->type !== $type && $field->hasMeasurements()) {
            throw ValidationException::withMessages([
                'type' => [__('unit_measurements.errors.type_locked')],
            ]);
        }

        if ($field !== null && $field->exists && $type->usesOptionList()) {
            self::assertOptionsStillCoverInUse($field, $options ?? []);
        }

        $minValue = $type === UnitMeasureFieldType::Numeric
            && array_key_exists('min_value', $input)
            && $input['min_value'] !== null
            && $input['min_value'] !== ''
            ? (float) $input['min_value']
            : null;
        $maxValue = $type === UnitMeasureFieldType::Numeric
            && array_key_exists('max_value', $input)
            && $input['max_value'] !== null
            && $input['max_value'] !== ''
            ? (float) $input['max_value']
            : null;

        if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
            throw ValidationException::withMessages([
                'max_value' => [__('unit_measurements.errors.min_max_order')],
            ]);
        }

        Validator::make(
            ['name' => $name],
            [
                'name' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('unit_measure_fields', 'name')
                        ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                        ->ignore($field?->id),
                ],
            ],
            [
                'name.unique' => __('unit_measurements.errors.name_unique'),
            ],
        )->validate();

        return new SaveUnitMeasureFieldData(
            name: $name,
            type: $type,
            unitOfMeasure: $unitOfMeasure,
            minValue: $minValue,
            maxValue: $maxValue,
            options: $options,
            isActive: array_key_exists('is_active', $input) ? (bool) $input['is_active'] : true,
        );
    }

    /**
     * @param  list<mixed>|null  $options
     * @return list<string>
     */
    public static function normalizeOptions(?array $options): array
    {
        if ($options === null) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (! is_string($option) && ! is_numeric($option)) {
                continue;
            }
            $trimmed = trim((string) $option);
            if ($trimmed !== '' && ! in_array($trimmed, $normalized, true)) {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $options
     */
    public static function assertOptionsStillCoverInUse(UnitMeasureField $field, array $options): void
    {
        foreach ($field->choiceOptionsWithMeasurements() as $inUse) {
            if (! in_array($inUse, $options, true)) {
                throw ValidationException::withMessages([
                    'options' => [__('unit_measurements.errors.option_in_use', ['option' => $inUse])],
                ]);
            }
        }
    }
}
