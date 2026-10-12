<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerRequest extends FormRequest
{
    public const WEEKLY_CONTRACT_MINUTES_MAX = 10080;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function weeklyContractMinuteRules(): array
    {
        return ['nullable', 'integer', 'min:0', 'max:'.self::WEEKLY_CONTRACT_MINUTES_MAX];
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
            'default_unit_id' => ['nullable', 'integer'],
            'weekly_contract_minutes' => self::weeklyContractMinuteRules(),
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
            'default_unit_id.integer' => __('team.errors.worker_default_unit_invalid'),
            'weekly_contract_minutes.max' => __('team.errors.worker_contract_minutes_max'),
            'weekly_contract_minutes.min' => __('team.errors.worker_contract_minutes_max'),
            'weekly_contract_minutes.integer' => __('team.errors.worker_contract_minutes_max'),
        ];
    }
}
