<?php

namespace App\Http\Requests\Time;

use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
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
            'kind' => ['required', Rule::enum(ShiftTypeKind::class)],
            'start_time' => ['required_if:kind,'.ShiftTypeKind::Work->value, 'nullable', 'string', 'max:5'],
            'end_time' => ['required_if:kind,'.ShiftTypeKind::Work->value, 'nullable', 'string', 'max:5'],
            'break_minutes' => ['required_if:kind,'.ShiftTypeKind::Work->value, 'nullable', 'integer', 'min:0', 'max:720'],
            'color' => ['required', Rule::enum(ShiftTypeColor::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
