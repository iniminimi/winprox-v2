<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization.required' => __('auth.errors.organization_required'),
            'name.required' => __('auth.errors.name_required'),
            'email.required' => __('auth.errors.email_required'),
            'email.email' => __('auth.errors.email_invalid'),
            'email.unique' => __('auth.errors.email_taken'),
            'password.required' => __('auth.errors.password_required'),
            'password.confirmed' => __('auth.errors.password_confirmed'),
            'password.min' => __('auth.errors.password_min'),
        ];
    }
}
