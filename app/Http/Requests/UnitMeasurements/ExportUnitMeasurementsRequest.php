<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitMeasurements;

use Illuminate\Foundation\Http\FormRequest;

class ExportUnitMeasurementsRequest extends FormRequest
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
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'location' => ['nullable', 'integer', 'min:1'],
            'field' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:200'],
        ];
    }
}
