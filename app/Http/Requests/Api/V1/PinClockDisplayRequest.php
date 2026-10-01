<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PinClockDisplayRequest extends FormRequest
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
            // Zelfde formaat als de portaal-PIN (WorkerClockPinRequest).
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ];
    }
}
