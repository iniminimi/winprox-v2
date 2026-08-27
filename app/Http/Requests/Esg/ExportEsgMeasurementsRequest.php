<?php

declare(strict_types=1);

namespace App\Http\Requests\Esg;

use Illuminate\Foundation\Http\FormRequest;

class ExportEsgMeasurementsRequest extends FormRequest
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
            'indicator' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'integer', 'min:1'],
            'unit' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'alarms' => ['nullable'],
        ];
    }
}
