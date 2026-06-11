<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class SendTeamQrEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'recipientEmail' => ['required', 'string', 'email', 'max:255'],
            'recipientName' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'recipientEmail.required' => __('team.qr.email_recipient_required'),
            'recipientEmail.email' => __('team.errors.worker_email_invalid'),
        ];
    }
}
