<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'guest_first_name' => ['required', 'string', 'min:1', 'max:100'],
            'guest_last_name' => ['required', 'string', 'min:1', 'max:100'],
            'guest_email' => ['required', 'email', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'worker_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }
}
