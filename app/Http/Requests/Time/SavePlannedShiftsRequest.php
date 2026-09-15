<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class SavePlannedShiftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'week_start' => ['required', 'date'],
            'worker_ids' => ['required', 'array'],
            'worker_ids.*' => ['integer'],
            'cells' => ['present', 'array'],
            'cells.*.worker_id' => ['required', 'integer'],
            'cells.*.date' => ['required', 'date'],
            'cells.*.raw' => ['nullable', 'string', 'max:32'],
        ];
    }
}
