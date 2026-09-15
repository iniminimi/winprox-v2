<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class CopyWeekRequest extends FormRequest
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
            'source_week_start' => ['required', 'date'],
            'target_week_start' => ['required', 'date', 'different:source_week_start'],
            'worker_ids' => ['required', 'array'],
            'worker_ids.*' => ['integer'],
        ];
    }
}
