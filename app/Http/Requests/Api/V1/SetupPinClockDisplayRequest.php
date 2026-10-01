<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SetupPinClockDisplayRequest extends FormRequest
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
            'worker_id' => ['required', 'integer', 'min:1'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirm' => ['required', 'string', 'same:pin'],
        ];
    }
}
