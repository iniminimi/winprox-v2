<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\TenantStarterPackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyTenantStarterPackRequest extends FormRequest
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
            'starterPackType' => ['required', 'string', Rule::enum(TenantStarterPackType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'starterPackType.required' => __('dashboard.starter_pack.errors.type_required'),
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
