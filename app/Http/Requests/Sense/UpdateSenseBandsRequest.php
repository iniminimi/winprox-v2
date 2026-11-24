<?php

declare(strict_types=1);

namespace App\Http\Requests\Sense;

use App\Enums\SenseMetric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSenseBandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'metric' => ['required', 'string', Rule::in([
                SenseMetric::Temperature->value,
                SenseMetric::Humidity->value,
                SenseMetric::Co2->value,
                SenseMetric::Voc->value,
            ])],
            'low' => ['nullable', 'numeric'],
            'target' => ['nullable', 'numeric'],
            'high' => ['required', 'numeric'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validatePayload(array $input): array
    {
        $validator = validator($input, self::ruleSet());
        $validator->after(function (Validator $validator) use ($input): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $metric = SenseMetric::from((string) $input['metric']);
            $range = config('sense.ranges.'.$metric->value);
            $min = (float) $range['min'];
            $max = (float) $range['max'];
            $step = (float) $range['step'];
            $high = (float) $input['high'];

            if (! self::onStep($high, $min, $step) || $high < $min || $high > $max) {
                $validator->errors()->add('high', __('sense.errors.band_range'));

                return;
            }

            if ($metric->isCeiling()) {
                return;
            }

            $low = (float) ($input['low'] ?? 0);
            $target = (float) ($input['target'] ?? 0);

            foreach (['low' => $low, 'target' => $target] as $key => $value) {
                if (! self::onStep($value, $min, $step) || $value < $min || $value > $max) {
                    $validator->errors()->add($key, __('sense.errors.band_range'));

                    return;
                }
            }

            if (! ($low < $target && $target < $high)) {
                $validator->errors()->add('target', __('sense.errors.band_order'));
            }
        });

        return $validator->validate();
    }

    private static function onStep(float $value, float $min, float $step): bool
    {
        if ($step <= 0) {
            return false;
        }

        $steps = round(($value - $min) / $step);

        return abs(($value - $min) - ($steps * $step)) < 0.001;
    }
}
