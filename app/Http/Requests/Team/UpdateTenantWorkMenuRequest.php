<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantWorkMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function ruleSet(): array
    {
        return [
            'work_menu_calendar_enabled' => ['required', 'boolean'],
            'work_menu_reservations_enabled' => ['required', 'boolean'],
            'work_menu_inspection_rounds_enabled' => ['required', 'boolean'],
            'work_menu_unit_measurements_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }
}
