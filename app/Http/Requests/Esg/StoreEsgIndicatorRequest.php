<?php

namespace App\Http\Requests\Esg;

use App\Enums\EsgIndicatorType;
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
     * @param  array{name: string, type: string, unit_of_measure?: ?string, threshold_min?: mixed, threshold_max?: mixed}  $validated
     * @return array{name: string, type: string, unit_of_measure: ?string, thresholds: ?array{min?: float, max?: float}}
     */
    public static function toActionPayload(array $validated): array
    {
        self::assertThresholdRange($validated);

        return [
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'unit_of_measure' => filled($validated['unit_of_measure'] ?? null)
                ? trim((string) $validated['unit_of_measure'])
                : null,
            'thresholds' => self::thresholdsFromValidated($validated),
        ];
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
