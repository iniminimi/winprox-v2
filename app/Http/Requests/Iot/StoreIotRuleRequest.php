<?php

declare(strict_types=1);

namespace App\Http\Requests\Iot;

use App\Enums\IotRuleOperator;
use App\Enums\TaskPriority;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIotRuleRequest extends FormRequest
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
            'iot_sensor_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'operator' => ['required', 'string', Rule::in(IotRuleOperator::values())],
            'threshold' => ['required', 'numeric'],
            'description' => ['required', 'string', 'max:'.TextDescriptionLimits::MAX],
            'internal_team_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
        ];
    }
}
