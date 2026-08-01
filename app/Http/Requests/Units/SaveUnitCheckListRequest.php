<?php

declare(strict_types=1);

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUnitCheckListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function staticRules(?int $tenantId = null): array
    {
        $teamRules = ['nullable', 'integer'];
        if ($tenantId !== null) {
            $teamRules[] = Rule::exists('internal_teams', 'id')->where(
                fn ($q) => $q->where('tenant_id', $tenantId),
            );
        }

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'items' => ['required'],
            'is_active' => ['sometimes', 'boolean'],
            'internal_team_id' => $teamRules,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'name.required' => __('unit_checks.lists.errors.name_required'),
            'items.required' => __('unit_checks.lists.errors.items_required'),
            'internal_team_id.exists' => __('unit_checks.lists.errors.invalid_team'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        return self::staticRules($tenantId ? (int) $tenantId : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }
}
