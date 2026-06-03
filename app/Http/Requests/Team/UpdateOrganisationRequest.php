<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganisationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:32'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:128'],
            'country_code' => ['nullable', 'string', 'regex:/^$|^[A-Z]{2}$/'],
            'custom_theme_active' => ['nullable', 'boolean'],
            'custom_theme_bg' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'custom_theme_btn' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('settings.errors.organisation_name_required'),
            'email.email' => __('settings.errors.organisation_email_invalid'),
            'country_code.regex' => __('settings.errors.organisation_country_invalid'),
            'custom_theme_bg.regex' => __('settings.errors.custom_theme_hex_invalid'),
            'custom_theme_btn.regex' => __('settings.errors.custom_theme_hex_invalid'),
        ];
    }
}
