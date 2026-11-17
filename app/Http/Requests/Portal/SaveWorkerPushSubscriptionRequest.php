<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkerPushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:1024', 'starts_with:https://'],
            'public_key' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'auth_token' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'locale' => ['required', 'string', Rule::in(config('locales.portal'))],
        ];
    }
}
