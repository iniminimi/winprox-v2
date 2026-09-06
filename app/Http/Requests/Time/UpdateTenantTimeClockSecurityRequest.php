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
        ];
    }
}
