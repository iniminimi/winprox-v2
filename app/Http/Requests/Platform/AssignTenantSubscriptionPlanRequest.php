<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Support\Billing\BillingCatalogViewData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTenantSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $planKeys = BillingCatalogViewData::platformPlanKeys();

        return [
            'plan' => ['required', 'string', Rule::in($planKeys)],
            'units_cap' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => __('platform.errors.plan_required'),
            'plan.in' => __('platform.errors.plan_invalid'),
            'units_cap.integer' => __('platform.errors.units_cap_invalid'),
            'units_cap.min' => __('platform.errors.units_cap_invalid'),
            'units_cap.max' => __('platform.errors.units_cap_invalid'),
        ];
    }
}
