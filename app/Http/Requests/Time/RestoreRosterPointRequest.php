<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class RestoreRosterPointRequest extends FormRequest
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
            'restore_point_id' => ['required', 'integer'],
            'confirmed' => ['accepted'],
            'seen_token' => ['required', 'string', 'size:64'],
        ];
    }
}
