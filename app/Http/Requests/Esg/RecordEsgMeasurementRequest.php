<?php

declare(strict_types=1);

namespace App\Http\Requests\Esg;

use App\Data\Esg\RecordEsgMeasurementData;
use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class RecordEsgMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', EsgMeasurement::class);
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
            'worker_id' => [
                'nullable',
                'integer',
                Rule::exists('workers', 'id')->where($tenantScope),
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

        if ($type === EsgIndicatorType::Choice) {
            self::assertChoiceValueAllowed((string) $validated['value_string'], $indicator);
        }
    }

    private static function assertChoiceValueAllowed(string $value, EsgIndicator $indicator): void
    {
        $options = $indicator->normalizedChoiceOptions();
        if ($options === [] || ! in_array($value, $options, true)) {
            throw ValidationException::withMessages([
                'value_string' => [__('esg.errors.measurement_choice_invalid')],
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
     * @return array<string, array<int, mixed>>
     */
    public static function portalRuleSet(EsgIndicatorType $type): array
    {
        return array_merge(
            ['completingRecordedAt' => ['required', 'date']],
            self::portalValueRuleSet($type),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function portalValueRuleSet(EsgIndicatorType $type): array
    {
        return match ($type) {
            EsgIndicatorType::Numeric => ['completingEsgValueNumeric' => ['required', 'numeric']],
            EsgIndicatorType::Boolean => ['completingEsgValueBoolean' => ['required', 'boolean']],
            EsgIndicatorType::String, EsgIndicatorType::Choice => ['completingEsgValueString' => ['required', 'string', 'max:500']],
            EsgIndicatorType::Json => ['completingEsgValueJson' => ['required', 'string', 'json']],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function portalValidationMessages(EsgIndicatorType $type): array
    {
        $valueField = match ($type) {
            EsgIndicatorType::Numeric => 'completingEsgValueNumeric',
            EsgIndicatorType::Boolean => 'completingEsgValueBoolean',
            EsgIndicatorType::String, EsgIndicatorType::Choice => 'completingEsgValueString',
            EsgIndicatorType::Json => 'completingEsgValueJson',
        };

        return [
            'completingRecordedAt.required' => __('esg.errors.measurement_recorded_at_required'),
            'completingRecordedAt.date' => __('esg.errors.measurement_recorded_at_required'),
            "{$valueField}.required" => __('esg.errors.measurement_value_required'),
            "{$valueField}.numeric" => __('esg.errors.measurement_value_wrong_type'),
            "{$valueField}.boolean" => __('esg.errors.measurement_value_wrong_type'),
            "{$valueField}.string" => __('esg.errors.measurement_value_wrong_type'),
            "{$valueField}.max" => __('esg.errors.measurement_value_wrong_type'),
            "{$valueField}.json" => __('esg.errors.measurement_value_wrong_type'),
        ];
    }

    public static function assertPortalRecordedAt(string $recordedAt, Validator $validator): void
    {
        try {
            $parsed = \Carbon\CarbonImmutable::parse($recordedAt);
        } catch (\Throwable) {
            return;
        }

        if ($parsed->greaterThan(now()->addMinutes(5))) {
            $validator->errors()->add('completingRecordedAt', __('esg.errors.measurement_recorded_at_required'));
        }
    }

    /**
     * @param  array<string, mixed>  $portalInput
     */
    public static function portalToData(
        int $taskId,
        EsgIndicator $indicator,
        string $recordedAt,
        array $portalInput,
    ): RecordEsgMeasurementData {
        $jsonValue = $portalInput['completingEsgValueJson'] ?? null;
        if (is_string($jsonValue) && $jsonValue !== '') {
            $decoded = json_decode($jsonValue, true);
            $jsonValue = is_array($decoded) ? $decoded : null;
        } else {
            $jsonValue = null;
        }

        return self::toData([
            'task_id' => $taskId,
            'esg_indicator_id' => $indicator->id,
            'recorded_at' => $recordedAt,
            'value_numeric' => $portalInput['completingEsgValueNumeric'] ?? null,
            'value_boolean' => $portalInput['completingEsgValueBoolean'] ?? null,
            'value_string' => $portalInput['completingEsgValueString'] ?? null,
            'value_json' => is_array($jsonValue) ? $jsonValue : null,
        ], $indicator);
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
