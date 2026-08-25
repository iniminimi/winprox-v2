<?php

namespace App\Http\Requests\Auth;

use App\Support\CountryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'organization' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'regex:/^$|^[A-Z]{2}$/', Rule::in(array_merge([''], CountryOptions::codes()))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'accept_terms' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization.required' => __('auth.errors.organization_required'),
            'phone.regex' => __('auth.errors.phone_invalid'),
            'country_code.regex' => __('auth.errors.country_code_invalid'),
            'country_code.in' => __('auth.errors.country_code_invalid'),
            'name.required' => __('auth.errors.name_required'),
            'email.required' => __('auth.errors.email_required'),
            'email.email' => __('auth.errors.email_invalid'),
            'email.unique' => __('auth.errors.email_taken'),
            'password.required' => __('auth.errors.password_required'),
            'password.confirmed' => __('auth.errors.password_confirmed'),
            'password.min' => __('auth.errors.password_min'),
            'accept_terms.accepted' => __('auth.errors.accept_terms_required'),
        ];
    }
}
