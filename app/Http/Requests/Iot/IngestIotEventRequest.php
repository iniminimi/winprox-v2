<?php

declare(strict_types=1);

namespace App\Http\Requests\Iot;

use App\Enums\IotEventKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestIotEventRequest extends FormRequest
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
            'external_sensor_id' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', Rule::in(IotEventKind::values())],
            'value' => ['nullable', 'numeric'],
            'occurred_at' => ['required', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
