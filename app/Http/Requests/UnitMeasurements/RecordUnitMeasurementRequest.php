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
use Illuminate\Validation\ValidationException;
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
     * Build a validated value payload from a portal raw input for one field.
     *
     * @return array{
     *     unit_measure_field_id: int,
     *     value_numeric?: float,
     *     value_boolean?: bool,
     *     value_string?: string
     * }
     */
    public static function portalEntryFromRaw(UnitMeasureField $field, mixed $raw): array
    {
        $entry = ['unit_measure_field_id' => (int) $field->id];

        if ($field->type === UnitMeasureFieldType::Numeric) {
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                throw ValidationException::withMessages([
                    'value_numeric' => [__('unit_measurements.errors.value_required')],
                    'fields.'.$field->id => [__('unit_measurements.errors.value_required')],
                ]);
            }
            $entry['value_numeric'] = (float) $raw;
        } elseif ($field->type === UnitMeasureFieldType::Boolean) {
            if ($raw === null || $raw === '') {
                throw ValidationException::withMessages([
                    'value_boolean' => [__('unit_measurements.errors.value_required')],
                    'fields.'.$field->id => [__('unit_measurements.errors.value_required')],
                ]);
            }
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                throw ValidationException::withMessages([
                    'value_boolean' => [__('unit_measurements.errors.value_required')],
                    'fields.'.$field->id => [__('unit_measurements.errors.value_required')],
                ]);
            }
            $entry['value_boolean'] = $bool;
        } else {
            $string = is_string($raw) || is_numeric($raw) ? trim((string) $raw) : '';
            if ($string === '') {
                throw ValidationException::withMessages([
                    'value_string' => [__('unit_measurements.errors.value_required')],
                    'fields.'.$field->id => [__('unit_measurements.errors.value_required')],
                ]);
            }
            $entry['value_string'] = $string;
        }

        self::assertValueMatchesField($entry, $field);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertValueMatchesField(
        array $validated,
        UnitMeasureField $field,
        ?Validator $validator = null,
    ): void {
        $errors = self::valueMatchErrors($validated, $field);

        if ($errors === []) {
            return;
        }

        if ($validator !== null) {
            foreach ($errors as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }

            return;
        }

        throw ValidationException::withMessages($errors);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, list<string>>
     */
    public static function valueMatchErrors(array $validated, UnitMeasureField $field): array
    {
        $column = $field->type->valueColumn();
        $fieldKey = 'fields.'.$field->id;
        $errors = [];

        $hasValue = match ($field->type) {
            UnitMeasureFieldType::Numeric => array_key_exists('value_numeric', $validated)
                && $validated['value_numeric'] !== null
                && $validated['value_numeric'] !== '',
            UnitMeasureFieldType::Boolean => array_key_exists('value_boolean', $validated)
                && $validated['value_boolean'] !== null,
            UnitMeasureFieldType::String, UnitMeasureFieldType::Choice => filled($validated['value_string'] ?? null),
        };

        if (! $hasValue) {
            $message = __('unit_measurements.errors.value_required');
            $errors[$column] = [$message];
            $errors[$fieldKey] = [$message];

            return $errors;
        }

        if ($field->type === UnitMeasureFieldType::Numeric) {
            $value = (float) $validated['value_numeric'];
            if ($field->min_value !== null && $value < (float) $field->min_value) {
                $message = __('unit_measurements.errors.value_below_min', ['min' => $field->min_value]);
                $errors[$column] = [$message];
                $errors[$fieldKey] = [$message];
            }
            if ($field->max_value !== null && $value > (float) $field->max_value) {
                $message = __('unit_measurements.errors.value_above_max', ['max' => $field->max_value]);
                $errors[$column] = [$message];
                $errors[$fieldKey] = [$message];
            }
        }

        if ($field->type === UnitMeasureFieldType::Choice) {
            $options = $field->normalizedChoiceOptions();
            if (! in_array((string) $validated['value_string'], $options, true)) {
                $message = __('unit_measurements.errors.value_choice_invalid');
                $errors[$column] = [$message];
                $errors[$fieldKey] = [$message];
            }
        }

        if ($field->type === UnitMeasureFieldType::String
            && mb_strlen((string) $validated['value_string']) > 500) {
            $message = __('unit_measurements.errors.value_string_too_long');
            $errors[$column] = [$message];
            $errors[$fieldKey] = [$message];
        }

        return $errors;
    }
}
