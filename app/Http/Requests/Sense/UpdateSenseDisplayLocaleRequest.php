<?php

declare(strict_types=1);

namespace App\Http\Requests\Sense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSenseDisplayLocaleRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::in(config('locales.supported'))],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{locale: string}
     */
    public static function validatePayload(array $input): array
    {
        /** @var array{locale: string} $validated */
        $validated = validator($input, self::ruleSet())->validate();

        return $validated;
    }
}
