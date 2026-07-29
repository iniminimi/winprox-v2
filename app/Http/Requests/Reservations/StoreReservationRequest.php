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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'unit_id.required' => __('reservations.errors.unit_required'),
            'unit_id.exists' => __('reservations.errors.unit_required'),
            'guest_first_name.required' => __('reservations.errors.first_name_required'),
            'guest_last_name.required' => __('reservations.errors.last_name_required'),
            'guest_email.required' => __('reservations.errors.email_required'),
            'guest_email.email' => __('reservations.errors.email_invalid'),
            'start_at.required' => __('reservations.errors.start_required'),
            'start_at.date' => __('reservations.errors.start_required'),
            'end_at.required' => __('reservations.errors.end_required'),
            'end_at.date' => __('reservations.errors.end_required'),
            'end_at.after' => __('reservations.errors.end_after_start'),
        ];
    }
}
