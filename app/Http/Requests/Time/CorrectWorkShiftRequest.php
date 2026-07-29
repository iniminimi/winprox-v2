<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class CorrectWorkShiftRequest extends FormRequest
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
            'clock_in_at' => ['required', 'date'],
            'clock_out_at' => ['required', 'date', 'after:clock_in_at'],
            'total_break_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clock_out_at.after' => __('time.corrections.errors.clock_out_before_clock_in'),
        ];
    }
}
