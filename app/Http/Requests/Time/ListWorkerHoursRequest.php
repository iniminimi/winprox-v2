<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class ListWorkerHoursRequest extends FormRequest
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
            'month' => ['required', 'date_format:Y-m'],
        ];
    }
}
