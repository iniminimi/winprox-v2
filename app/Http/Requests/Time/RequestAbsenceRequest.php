<?php

namespace App\Http\Requests\Time;

use App\Enums\ShiftTypeKind;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestAbsenceRequest extends FormRequest
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
            'kind' => ['required', Rule::in([ShiftTypeKind::Leave->value, ShiftTypeKind::Recup->value])],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'description' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }
}
