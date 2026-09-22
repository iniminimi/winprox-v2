<?php

namespace App\Http\Requests\Time;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class DecideAbsenceRequestRequest extends FormRequest
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
    public static function rulesFor(bool $approve = false): array
    {
        return [
            'reason' => $approve
                ? ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX]
                : ['required', 'string', 'min:3', 'max:'.TextDescriptionLimits::MAX],
        ];
    }
}
