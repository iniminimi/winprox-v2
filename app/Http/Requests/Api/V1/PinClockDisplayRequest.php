<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ClockDisplayPinAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Optioneel: nieuwe firmware stuurt in/out/break_*; oud = toggle.
            'action' => ['sometimes', 'nullable', 'string', Rule::enum(ClockDisplayPinAction::class)],
        ];
    }
}
