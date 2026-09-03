<?php

namespace App\Http\Requests\Time;

use App\Enums\PresenceComplianceScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresenceComplianceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'presence_compliance_enabled' => ['sometimes', 'boolean'],
            'presence_compliance_scope' => ['nullable', Rule::enum(PresenceComplianceScope::class)],
            'enterprise_number' => ['nullable', 'regex:/^[01]?\d{9,10}$/'],
            'foreign_vat_number' => ['nullable', 'string', 'max:255'],
            'presence_rsz_client_id' => ['nullable', 'string', 'max:255'],
            'presence_rsz_private_key' => ['nullable', 'string', 'max:10000'],
            'clear_private_key' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'enterprise_number.regex' => __('settings.errors.enterprise_number_invalid'),
            'presence_compliance_scope.Illuminate\Validation\Rules\Enum' => __('settings.errors.presence_scope_invalid'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }
}
