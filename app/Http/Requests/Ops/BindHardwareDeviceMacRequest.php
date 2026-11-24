<?php

declare(strict_types=1);

namespace App\Http\Requests\Ops;

use Illuminate\Foundation\Http\FormRequest;

class BindHardwareDeviceMacRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /** @return array<string, mixed> */
    public static function ruleSet(): array
    {
        return [
            'mac' => ['required', 'string', 'max:64'],
        ];
    }
}
