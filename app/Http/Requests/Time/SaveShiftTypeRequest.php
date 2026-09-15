<?php

namespace App\Http\Requests\Time;

use App\Enums\ShiftTypeColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveShiftTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:8'],
            'label' => ['required', 'string', 'max:80'],
            'start_time' => ['required', 'string', 'max:5'],
            'end_time' => ['required', 'string', 'max:5'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'color' => ['required', Rule::enum(ShiftTypeColor::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
