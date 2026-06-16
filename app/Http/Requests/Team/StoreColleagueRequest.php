<?php

namespace App\Http\Requests\Team;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColleagueRequest extends FormRequest
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
        return self::baseRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'locale' => ['required', Rule::in(config('locales.supported', []))],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],
            'send_account_email' => ['boolean'],
            'notify_on_new_issue_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageMap();
    }

    /**
     * @return array<string, string>
     */
    public static function messageMap(): array
    {
        return [
            'name.required' => __('team.errors.name_required'),
            'email.required' => __('team.errors.email_required'),
            'email.email' => __('team.errors.email_invalid'),
            'email.unique' => __('team.errors.email_taken'),
            'locale.required' => __('team.errors.locale_required'),
            'locale.in' => __('team.errors.locale_required'),
            'role.required' => __('team.errors.role_required'),
            'role.in' => __('team.errors.role_required'),
            'password.required' => __('team.errors.password_required'),
            'password.min' => __('team.errors.password_min'),
            'password_confirmation.required' => __('team.errors.password_confirm_required'),
            'password_confirmation.same' => __('auth.errors.password_confirmed'),
        ];
    }
}
