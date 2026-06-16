<?php

namespace App\Http\Requests\Team;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColleagueRequest extends FormRequest
{
    public ?int $userId = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::baseRules($this->userId);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function baseRules(?int $userId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'locale' => ['required', Rule::in(config('locales.supported', []))],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['nullable', 'string', 'min:8'],
            'password_confirmation' => ['nullable', 'same:password'],
            'notify_on_new_issue_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(StoreColleagueRequest::messageMap(), [
            'password.min' => __('team.errors.password_min'),
        ]);
    }
}
