<?php

namespace App\Http\Requests\TenantPurge;

use Illuminate\Foundation\Http\FormRequest;

class StartTenantPurgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'purge_password' => ['required', 'string'],
            'purge_export_ack' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purge_password.required' => __('subscription.purge.errors.password_required'),
            'purge_export_ack.accepted' => __('subscription.purge.errors.export_required'),
        ];
    }
}
