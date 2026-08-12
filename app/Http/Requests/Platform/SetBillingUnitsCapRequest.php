<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SetBillingUnitsCapRequest extends FormRequest
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
        return [
            'units_cap' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'units_cap.required' => __('platform.errors.units_cap_required'),
            'units_cap.integer' => __('platform.errors.units_cap_invalid'),
            'units_cap.min' => __('platform.errors.units_cap_invalid'),
            'units_cap.max' => __('platform.errors.units_cap_invalid'),
        ];
    }
}
