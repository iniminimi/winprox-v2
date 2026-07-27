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
    public static function ruleSet(?string $scheme = null): array
    {
        $scheme = $scheme ?? (string) request()->input('scheme', UnitBulkNaming::SCHEME_COMPACT_2);

        $rules = [
            'scheme' => ['required', Rule::in(UnitBulkNaming::schemes())],
            'prefix' => ['nullable', 'string', 'max:30'],
        ];

        if (UnitBulkNaming::isSequential($scheme)) {
            $maxRooms = $scheme === UnitBulkNaming::SCHEME_SEQUENTIAL_2
                ? UnitBulkNaming::MAX_SEQUENTIAL_2
                : UnitBulkNaming::MAX_SEQUENTIAL_3;
            $rules['floors'] = ['required', 'integer', 'min:0', 'max:9'];
            $rules['rooms_per_floor'] = ['required', 'integer', 'min:1', 'max:'.$maxRooms];

            return $rules;
        }

        $rules['floors'] = ['required', 'integer', 'min:1', 'max:10'];
        $rules['rooms_per_floor'] = ['required', 'integer', 'min:1', 'max:99'];

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet($this->input('scheme'));
    }
}
