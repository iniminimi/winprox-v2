<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('team.errors.worker_name_required'),
            'last_name.required' => __('team.errors.worker_name_required'),
            'email.email' => __('team.errors.worker_email_invalid'),
            'email.max' => __('team.errors.worker_email_max'),
            'phone.max' => __('team.errors.worker_phone_max'),
        ];
    }
}
