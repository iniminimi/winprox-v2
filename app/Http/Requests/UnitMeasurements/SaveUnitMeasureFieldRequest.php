<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
}
