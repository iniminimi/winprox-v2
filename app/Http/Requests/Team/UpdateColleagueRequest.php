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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'role' => ['required', Rule::in(User::ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('team.errors.name_required'),
            'email.required' => __('team.errors.email_required'),
            'email.email' => __('team.errors.email_invalid'),
            'email.unique' => __('team.errors.email_taken'),
            'role.required' => __('team.errors.role_required'),
            'role.in' => __('team.errors.role_required'),
        ];
    }
}
