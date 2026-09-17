<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class ApiStartWorkVisitRequest extends FormRequest
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
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
