<?php

namespace App\Http\Requests\Time;

use App\Enums\PresenceComplianceScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresenceComplianceSettingsRequest extends FormRequest
{
    /** 10 cijfers, eerste 0 of 1. Punten of spaties alleen als 0123.456.789. Geen BE-prefix. */
    public const ENTERPRISE_NUMBER_PATTERN = '/^(?:[01]\d{9}|[01]\d{3}[.\s]\d{3}[.\s]\d{3})$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function enterpriseNumberRules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'regex:'.self::ENTERPRISE_NUMBER_PATTERN,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'presence_compliance_scope' => ['nullable', Rule::enum(PresenceComplianceScope::class)],
            'enterprise_number' => self::enterpriseNumberRules(),
            'foreign_vat_number' => ['nullable', 'string', 'max:255'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageSet();
    }
}
