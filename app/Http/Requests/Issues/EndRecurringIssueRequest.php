<?php

declare(strict_types=1);

namespace App\Http\Requests\Issues;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class EndRecurringIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'endReason' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'endReason.required' => __('issues.errors.end_recurring_reason_required'),
            'endReason.min' => __('issues.errors.end_recurring_reason_min'),
            'endReason.max' => __('issues.errors.text_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageSet();
    }
}
