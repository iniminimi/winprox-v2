<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SetPlatformUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rulesFor(): array
    {
        return [
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        return [
            'password.required' => __('team.errors.password_required'),
            'password.min' => __('team.errors.password_min'),
            'password_confirmation.required' => __('team.errors.password_confirm_required'),
            'password_confirmation.same' => __('auth.errors.password_confirmed'),
        ];
    }
}
