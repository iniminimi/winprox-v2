<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantLoginAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function ruleSet(): array
    {
        return [
            'loginAlertEnabled' => ['boolean'],
            'loginAlertStart' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'loginAlertEnd' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
    }
}
