<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDemoRequestRequest extends FormRequest
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
            'company' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'phone' => ['required', 'regex:/^\+?[0-9\s\-()]{8,25}$/'],
            'municipality' => ['required', 'string', 'max:120'],
            'preference' => ['required', Rule::in(['onsite', 'video'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company.required' => __('demo.errors.company_required'),
            'name.required' => __('demo.errors.name_required'),
            'email.required' => __('demo.errors.email_required'),
            'email.email' => __('demo.errors.email_invalid'),
            'phone.required' => __('demo.errors.phone_required'),
            'phone.regex' => __('demo.errors.phone_invalid'),
            'municipality.required' => __('demo.errors.municipality_required'),
            'preference.required' => __('demo.errors.preference_required'),
            'preference.in' => __('demo.errors.preference_required'),
        ];
    }
}
