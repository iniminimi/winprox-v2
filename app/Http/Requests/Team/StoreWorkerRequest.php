<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerRequest extends FormRequest
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
            'is_external' => ['sometimes', 'boolean'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'ssin' => ['nullable', 'regex:/^\d{11}$/'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
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
            'company_name.max' => __('team.errors.worker_company_name_max'),
            'ssin.regex' => __('team.errors.worker_ssin_invalid'),
            'photo.image' => __('team.errors.worker_photo_invalid'),
            'photo.mimes' => __('team.errors.worker_photo_invalid'),
            'photo.max' => __('team.errors.worker_photo_max'),
        ];
    }
}
