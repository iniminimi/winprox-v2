<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
            'clocks_all_locations' => ['boolean'],
            'required_break_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'stale_shift_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'long_shift_hours' => ['required', 'integer', 'min:1', 'max:24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('team.errors.team_name_required'),
            'stale_shift_hours.max' => __('team.errors.stale_shift_hours_max'),
            'long_shift_hours.max' => __('team.errors.long_shift_hours_max'),
        ];
    }
}
