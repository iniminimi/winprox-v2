<?php

declare(strict_types=1);

namespace App\Http\Requests\Ops;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterHardwareDeviceRequest extends FormRequest
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
            'serial_number' => ['required', 'string', 'max:64'],
            'brand' => ['nullable', 'string', 'max:64'],
            'type' => ['nullable', 'string', Rule::enum(HardwareDeviceType::class)],
            'model' => ['nullable', 'string', 'max:64'],
            'sku' => ['nullable', 'string', 'max:64'],
            'condition' => ['nullable', 'string', Rule::enum(HardwareDeviceCondition::class)],
            'purchase_ref' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
