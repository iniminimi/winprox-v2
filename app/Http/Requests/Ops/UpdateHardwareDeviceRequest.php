<?php

declare(strict_types=1);

namespace App\Http\Requests\Ops;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHardwareDeviceRequest extends FormRequest
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
            'brand' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', Rule::enum(HardwareDeviceType::class)],
            'model' => ['nullable', 'string', 'max:64'],
            'sku' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::enum(HardwareDeviceStatus::class)],
            'condition' => ['required', 'string', Rule::enum(HardwareDeviceCondition::class)],
            'purchase_ref' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
