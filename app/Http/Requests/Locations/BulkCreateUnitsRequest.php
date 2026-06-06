<?php

namespace App\Http\Requests\Locations;

use App\Support\Units\UnitBulkNaming;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkCreateUnitsRequest extends FormRequest
{
    public const MAX_UNITS = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'floors' => ['required', 'integer', 'min:1', 'max:10'],
            'rooms_per_floor' => ['required', 'integer', 'min:1', 'max:99'],
            'scheme' => ['required', Rule::in([UnitBulkNaming::SCHEME_BLOCK_3, UnitBulkNaming::SCHEME_COMPACT_2])],
            'prefix' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }
}
