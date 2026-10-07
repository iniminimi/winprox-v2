<?php

namespace App\Http\Requests\Time;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class SavePlannedDayRequest extends FormRequest
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
            'worker_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'blocks' => ['present', 'array', 'max:8'],
            'blocks.*.id' => ['sometimes', 'nullable', 'integer'],
            'blocks.*.shift_type_id' => ['sometimes', 'nullable', 'integer'],
            'blocks.*.start_time' => ['sometimes', 'nullable', 'date_format:H:i', 'required_without:blocks.*.shift_type_id'],
            'blocks.*.end_time' => ['sometimes', 'nullable', 'date_format:H:i', 'required_without:blocks.*.shift_type_id'],
            'blocks.*.break_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:480'],
            'blocks.*.unit_id' => ['sometimes', 'nullable', 'integer'],
            'blocks.*.description' => ['sometimes', 'nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }
}
