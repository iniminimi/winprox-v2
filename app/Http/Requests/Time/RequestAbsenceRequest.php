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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.after_or_equal' => __('time.absence.errors.date_in_past'),
            'date_to.after_or_equal' => __('time.absence.errors.range_inverted'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => __('time.portal.absence.kind'),
            'date_from' => __('time.portal.absence.date_from'),
            'date_to' => __('time.portal.absence.date_to'),
            'description' => __('time.portal.absence.description'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'kind' => ['required', Rule::in([ShiftTypeKind::Leave->value, ShiftTypeKind::Recup->value])],
            'date_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'description' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }
}
