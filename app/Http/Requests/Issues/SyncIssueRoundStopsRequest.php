<?php

namespace App\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncIssueRoundStopsRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet($tenantId ? (int) $tenantId : null);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null): array
    {
        return [
            'round_stop_unit_ids' => ['required', 'array', 'min:2'],
            'round_stop_unit_ids.*' => [
                'integer',
                Rule::exists('units', 'id')->when(
                    $tenantId !== null,
                    fn ($rule) => $rule
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->where('allow_unit_checks', true),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'round_stop_unit_ids.required' => __('issues.errors.round_stops_min'),
            'round_stop_unit_ids.min' => __('issues.errors.round_stops_min'),
            'round_stop_unit_ids.*.exists' => __('issues.errors.round_stops_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageSet();
    }
}
