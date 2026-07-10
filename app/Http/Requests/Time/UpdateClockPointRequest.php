<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClockPointRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
