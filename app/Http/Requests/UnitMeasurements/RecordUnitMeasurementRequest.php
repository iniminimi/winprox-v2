<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;
use App\Enums\UnitMeasurementSource;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordUnitMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', UnitMeasurement::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::staticRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $validated = $validator->validated();
            self::assertRecordedAt((string) $validated['recorded_at'], $validator);

            $field = UnitMeasureField::query()->find((int) $validated['unit_measure_field_id']);
            if ($field instanceof UnitMeasureField) {
                self::assertValueMatchesField($validated, $field, $validator);
            }
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function staticRules(): array
    {
        return [
            'unit_measure_field_id' => ['required', 'integer', 'exists:unit_measure_fields,id'],
            'recorded_at' => ['required', 'date'],
            'source' => ['sometimes', 'string', Rule::in(UnitMeasurementSource::values())],
            'value_numeric' => ['nullable', 'numeric'],
            'value_boolean' => ['nullable', 'boolean'],
            'value_string' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'unit_measure_field_id.required' => __('unit_measurements.validation.field_required'),
            'unit_measure_field_id.exists' => __('unit_measurements.validation.field_invalid'),
            'recorded_at.required' => __('unit_measurements.validation.recorded_at_required'),
            'recorded_at.date' => __('unit_measurements.validation.recorded_at_invalid'),
        ];
    }

    public static function assertRecordedAt(string $recordedAt, Validator $validator): void
    {
        try {
            $at = CarbonImmutable::parse($recordedAt);
        } catch (\Throwable) {
            $validator->errors()->add('recorded_at', __('unit_measurements.validation.recorded_at_invalid'));

            return;
        }

        if ($at->greaterThan(now()->addMinutes(5))) {
            $validator->errors()->add('recorded_at', __('unit_measurements.validation.recorded_at_future'));
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertValueMatchesField(array $validated, UnitMeasureField $field, Validator $validator): void
    {
        $column = $field->type->valueColumn();
        $hasValue = match ($field->type) {
            UnitMeasureFieldType::Numeric => array_key_exists('value_numeric', $validated) && $validated['value_numeric'] !== null && $validated['value_numeric'] !== '',
            UnitMeasureFieldType::Boolean => array_key_exists('value_boolean', $validated) && $validated['value_boolean'] !== null,
            UnitMeasureFieldType::String, UnitMeasureFieldType::Choice => filled($validated['value_string'] ?? null),
        };

        if (! $hasValue) {
            $validator->errors()->add($column, __('unit_measurements.errors.value_required'));
        }
    }
}
