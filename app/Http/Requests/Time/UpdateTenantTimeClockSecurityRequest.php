<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantTimeClockSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'time_require_worker_pin' => ['required', 'boolean'],
            'time_gps_on_clock' => ['required', 'boolean'],
            'time_gps_visits' => ['required', 'boolean'],
            'time_gps_visit_radius_meters' => ['nullable', 'integer', 'min:50', 'max:2000'],
            'time_evacuation_list' => ['required', 'boolean'],
        ];
    }
}
