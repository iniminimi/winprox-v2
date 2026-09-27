<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitClockDisplayClaimRequest extends FormRequest
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
            // Pairing-code zoals getoond: "XXXX-XXXX" — device mag hem ook
            // zonder streepje sturen; SubmitClockDisplayClaimAction normaliseert.
            'code' => ['required', 'string', 'min:8', 'max:12'],
            // Vrij gekozen door het device (bv. MAC) — géén vertrouwde identiteit,
            // alleen gebruikt voor idempotente retry op dezelfde pairing.
            'device_hint' => ['required', 'string', 'min:4', 'max:64'],
        ];
    }
}
