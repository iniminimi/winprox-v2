<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class ManualClockInWorkShiftRequest extends FormRequest
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
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'clock_point_id' => ['required', 'integer', 'exists:clock_points,id'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
