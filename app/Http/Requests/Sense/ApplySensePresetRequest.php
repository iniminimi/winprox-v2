<?php

declare(strict_types=1);

namespace App\Http\Requests\Sense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplySensePresetRequest extends FormRequest
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
            'preset' => ['required', 'string', Rule::in(array_keys(config('sense.presets', [])))],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validatePayload(array $input): array
    {
        return validator($input, self::ruleSet())->validate();
    }
}
