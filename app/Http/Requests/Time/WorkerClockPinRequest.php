<?php

namespace App\Http\Requests\Time;

use Illuminate\Foundation\Http\FormRequest;

class WorkerClockPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::verifyRulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function verifyRulesFor(): array
    {
        return [
            'pin_code' => ['required', 'regex:/^\d{4}$/'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function setupRulesFor(): array
    {
        return [
            'pin_code' => ['required', 'regex:/^\d{4}$/'],
            'pin_code_confirm' => ['required', 'same:pin_code'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'pin_code.required' => __('portal.worker.errors.pin_required'),
            'pin_code.regex' => __('portal.worker.errors.pin_invalid'),
            'pin_code_confirm.same' => __('portal.worker.errors.pin_mismatch'),
        ];
    }
}
