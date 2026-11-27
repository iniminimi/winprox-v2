<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Auth\BackofficePassword;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => [...BackofficePassword::constraints(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('auth.errors.email_required'),
            'email.email' => __('auth.errors.email_invalid'),
            'password.required' => __('auth.errors.password_required'),
            'password.confirmed' => __('auth.errors.password_confirmed'),
            'password.min' => __('auth.errors.password_min'),
            'password.uncompromised' => __('auth.errors.password_leaked'),
        ];
    }
}
