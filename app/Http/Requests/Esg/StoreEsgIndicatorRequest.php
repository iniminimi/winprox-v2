<?php

namespace App\Http\Requests\Esg;

use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class StoreEsgIndicatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null, ?int $ignoreIndicatorId = null): array
    {
        $unique = Rule::unique('esg_indicators', 'name');
        if ($tenantId !== null) {
            $unique = $unique->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }
        if ($ignoreIndicatorId !== null) {
            $unique = $unique->ignore($ignoreIndicatorId);
        }

        return [
            'name' => ['required', 'string', 'min:1', 'max:255', $unique],
            'type' => ['required', new Enum(EsgIndicatorType::class)],
            'unit_of_measure' => ['nullable', 'string', 'max:64'],
            'threshold_min' => ['nullable', 'numeric'],
            'threshold_max' => ['nullable', 'numeric'],
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

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function assertThresholdRange(array $validated): void
    {
        $min = $validated['threshold_min'] ?? null;
        $max = $validated['threshold_max'] ?? null;

        if ($min !== null && $max !== null && (float) $max < (float) $min) {
            throw ValidationException::withMessages([
                'threshold_max' => [__('esg.errors.threshold_max_lt_min')],
            ]);
        }
    }

    /**
     * @param  array{name: string, type: string, unit_of_measure?: ?string, threshold_min?: mixed, threshold_max?: mixed, choice_options?: list<string>}  $validated
     * @return array{name: string, type: string, unit_of_measure: ?string, thresholds: ?array{min?: float, max?: float}, options: ?list<string>}
     */
    public static function toActionPayload(array $validated, ?EsgIndicator $existing = null): array
    {
        self::assertThresholdRange($validated);

        $type = (string) $validated['type'];
        $options = null;
        if (EsgIndicatorType::from($type)->usesOptionList()) {
            $options = self::normalizeChoiceOptions($validated['choice_options'] ?? []);
            self::assertChoiceOptionsValid($options);
            if ($existing !== null) {
                self::assertChoiceOptionsRespectMeasurements($existing, $options);
            }
        }

        $unitOfMeasure = null;
        $thresholds = null;
        if ($type === EsgIndicatorType::Numeric->value) {
            $unitOfMeasure = filled($validated['unit_of_measure'] ?? null)
                ? trim((string) $validated['unit_of_measure'])
                : null;
            $thresholds = self::thresholdsFromValidated($validated);
        }

        return [
            'name' => trim($validated['name']),
            'type' => $type,
            'unit_of_measure' => $unitOfMeasure,
            'thresholds' => $thresholds,
            'options' => $options,
        ];
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    public static function normalizeChoiceOptions(array $raw): array
    {
        $options = [];
        foreach ($raw as $option) {
            $trimmed = trim((string) $option);
            if ($trimmed !== '') {
                $options[] = $trimmed;
            }
        }

        return array_values($options);
    }

    /**
     * @param  list<string>  $options
     */
    public static function assertChoiceOptionsValid(array $options): void
    {
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'choiceOptions' => [__('esg.errors.options_min')],
            ]);
        }

        if (count($options) !== count(array_unique($options))) {
            throw ValidationException::withMessages([
                'choiceOptions' => [__('esg.errors.options_duplicate')],
            ]);
        }
    }

    /**
     * @param  list<string>  $newOptions
     */
    public static function assertChoiceOptionsRespectMeasurements(EsgIndicator $indicator, array $newOptions): void
    {
        if (! $indicator->type->usesOptionList()) {
            return;
        }

        $removedInUse = [];
        foreach ($indicator->normalizedChoiceOptions() as $oldOption) {
            if (in_array($oldOption, $newOptions, true)) {
                continue;
            }

            if ($indicator->optionValueInUse($oldOption)) {
                $removedInUse[] = $oldOption;
            }
        }

        if ($removedInUse !== []) {
            throw ValidationException::withMessages([
                'choiceOptions' => [__('esg.errors.options_in_use', ['options' => implode(', ', $removedInUse)])],
            ]);
        }
    }

    /**
     * @param  array{threshold_min?: mixed, threshold_max?: mixed}  $validated
     * @return ?array{min?: float, max?: float}
     */
    public static function thresholdsFromValidated(array $validated): ?array
    {
        $min = $validated['threshold_min'] ?? null;
        $max = $validated['threshold_max'] ?? null;

        if ($min === null && $max === null) {
            return null;
        }

        $thresholds = [];
        if ($min !== null && $min !== '') {
            $thresholds['min'] = (float) $min;
        }
        if ($max !== null && $max !== '') {
            $thresholds['max'] = (float) $max;
        }

        return $thresholds === [] ? null : $thresholds;
    }
}
