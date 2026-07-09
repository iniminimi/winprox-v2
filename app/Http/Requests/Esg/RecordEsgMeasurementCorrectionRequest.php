<?php

declare(strict_types=1);

namespace App\Http\Requests\Esg;

use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class RecordEsgMeasurementCorrectionRequest extends FormRequest
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
        return self::ruleSet();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'recorded_at' => ['required', 'date'],
            'value_numeric' => ['nullable', 'numeric'],
            'value_boolean' => ['nullable', 'boolean'],
            'value_string' => ['nullable', 'string', 'max:500'],
            'value_json' => ['nullable', 'string', 'json'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function valueRules(EsgIndicatorType $type): array
    {
        return match ($type) {
            EsgIndicatorType::Numeric => ['correctionValueNumeric' => ['required', 'numeric']],
            EsgIndicatorType::Boolean => ['correctionValueBoolean' => ['required', 'boolean']],
            EsgIndicatorType::String, EsgIndicatorType::Choice => ['correctionValueString' => ['required', 'string', 'max:500']],
            EsgIndicatorType::MultiChoice => [
                'correctionValueMultiChoice' => ['required', 'array', 'min:1'],
                'correctionValueMultiChoice.*' => ['string', 'max:500'],
            ],
            EsgIndicatorType::Json => ['correctionValueJson' => ['required', 'string', 'json']],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(EsgIndicatorType $type): array
    {
        return [
            'recorded_at.required' => __('esg.errors.measurement_recorded_at_required'),
            'recorded_at.date' => __('esg.errors.measurement_recorded_at_required'),
            'correctionValueNumeric.required' => __('esg.errors.measurement_value_required'),
            'correctionValueBoolean.required' => __('esg.errors.measurement_value_required'),
            'correctionValueString.required' => __('esg.errors.measurement_value_required'),
            'correctionValueMultiChoice.required' => __('esg.errors.measurement_value_required'),
            'correctionValueMultiChoice.min' => __('esg.errors.measurement_value_required'),
            'correctionValueJson.required' => __('esg.errors.measurement_value_required'),
            'correctionValueJson.json' => __('esg.errors.measurement_value_wrong_type'),
        ];
    }

    /**
     * @param  array{
     *     recorded_at: string,
     *     value_numeric?: mixed,
     *     value_boolean?: mixed,
     *     value_string?: ?string,
     *     value_json?: ?array
     * }  $validated
     */
    public static function toData(EsgMeasurement $original, array $validated): RecordEsgMeasurementData
    {
        self::assertOriginalCanBeCorrected($original);

        $indicator = $original->relationLoaded('indicator')
            ? $original->indicator
            : $original->indicator()->first();

        if (! $indicator instanceof EsgIndicator) {
            throw ValidationException::withMessages([
                'measurement' => [__('esg.errors.measurement_indicator_invalid')],
            ]);
        }

        return RecordEsgMeasurementRequest::toData([
            'task_id' => $original->task_id,
            'esg_indicator_id' => $original->esg_indicator_id,
            'recorded_at' => $validated['recorded_at'],
            'value_numeric' => $validated['value_numeric'] ?? null,
            'value_boolean' => $validated['value_boolean'] ?? null,
            'value_string' => $validated['value_string'] ?? null,
            'value_json' => $validated['value_json'] ?? null,
            'corrects_measurement_id' => $original->id,
        ], $indicator);
    }

    /**
     * @param  array{
     *     correctionValueNumeric?: mixed,
     *     correctionValueBoolean?: mixed,
     *     correctionValueString?: ?string,
     *     correctionValueJson?: ?string,
     *     correctionRecordedAt: string
     * }  $input
     * @return array{
     *     recorded_at: string,
     *     value_numeric?: mixed,
     *     value_boolean?: mixed,
     *     value_string?: ?string,
     *     value_json?: ?array
     * }
     */
    public static function livewireToValidated(EsgMeasurement $original, array $input): array
    {
        self::assertOriginalCanBeCorrected($original);

        $jsonValue = $input['correctionValueJson'] ?? null;
        if (is_string($jsonValue) && $jsonValue !== '') {
            $decoded = json_decode($jsonValue, true);
            $jsonValue = is_array($decoded) ? $decoded : null;
        } else {
            $jsonValue = null;
        }

        $indicator = $original->relationLoaded('indicator')
            ? $original->indicator
            : $original->indicator()->first();

        if ($indicator?->type === EsgIndicatorType::MultiChoice) {
            $jsonValue = RecordEsgMeasurementRequest::normalizeMultiChoiceValues(
                is_array($input['correctionValueMultiChoice'] ?? null)
                    ? $input['correctionValueMultiChoice']
                    : null,
                $indicator,
            );
        }

        $valueBoolean = null;
        if (array_key_exists('correctionValueBoolean', $input)
            && $input['correctionValueBoolean'] !== null
            && $input['correctionValueBoolean'] !== '') {
            $valueBoolean = filter_var($input['correctionValueBoolean'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'recorded_at' => $input['correctionRecordedAt'],
            'value_numeric' => filled($input['correctionValueNumeric'] ?? null)
                ? $input['correctionValueNumeric']
                : null,
            'value_boolean' => $valueBoolean,
            'value_string' => filled($input['correctionValueString'] ?? null)
                ? (string) $input['correctionValueString']
                : null,
            'value_json' => is_array($jsonValue) ? $jsonValue : null,
        ];
    }

    public static function assertOriginalCanBeCorrected(EsgMeasurement $original): void
    {
        if ($original->corrects_measurement_id !== null) {
            throw ValidationException::withMessages([
                'measurement' => [__('esg.errors.measurement_correction_source_invalid')],
            ]);
        }
    }
}
