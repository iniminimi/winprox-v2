<?php

declare(strict_types=1);

namespace App\Http\Requests\Iot;

use App\Enums\IotSensorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIotSensorRequest extends FormRequest
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
        return self::ruleSet();
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'iot_gateway_id' => ['required', 'integer'],
            'external_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'sensor_type' => ['required', 'string', Rule::in(IotSensorType::values())],
            'location_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'esg_indicator_id' => ['nullable', 'integer'],
        ];
    }
}
