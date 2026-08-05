<?php

namespace App\Http\Requests\Issues;

use App\Enums\RecurrenceIntervalUnit;
use App\Enums\TaskPriority;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInspectionRoundRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet($tenantId ? (int) $tenantId : null);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:'.TextDescriptionLimits::MAX],
            'round_stop_unit_ids' => ['required', 'array', 'min:2'],
            'round_stop_unit_ids.*' => [
                'integer',
                Rule::exists('units', 'id')->when(
                    $tenantId !== null,
                    fn ($rule) => $rule
                        ->where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->where('allow_unit_checks', true),
                ),
            ],
            'recurrence_interval_value' => ['required', 'integer', 'min:1', 'max:24'],
            'recurrence_interval_unit' => ['required', Rule::enum(RecurrenceIntervalUnit::class)],
            'recurrence_lead_days' => ['required', 'integer', 'min:1', 'max:365'],
            'recurrence_first_due_date' => ['required', 'date', 'after_or_equal:today'],
            'internal_team_id' => ['required', 'integer', 'exists:internal_teams,id'],
            'task_note' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
            'task_priority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'description.required' => __('issues.errors.description_required'),
            'description.min' => __('issues.errors.description_min'),
            'description.max' => __('issues.errors.description_max'),
            'round_stop_unit_ids.required' => __('issues.errors.round_stops_min'),
            'round_stop_unit_ids.min' => __('issues.errors.round_stops_min'),
            'round_stop_unit_ids.*.exists' => __('issues.errors.round_stops_invalid'),
            'round_stop_unit_ids.*.integer' => __('issues.errors.round_stops_invalid'),
            'recurrence_interval_value.required' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_value.min' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_value.max' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_unit.required' => __('issues.errors.recurrence_unit_required'),
            'recurrence_lead_days.required' => __('issues.errors.recurrence_lead_required'),
            'recurrence_lead_days.min' => __('issues.errors.recurrence_lead_required'),
            'recurrence_lead_days.max' => __('issues.errors.recurrence_lead_required'),
            'recurrence_first_due_date.required' => __('issues.errors.recurrence_due_required'),
            'recurrence_first_due_date.after_or_equal' => __('issues.errors.recurrence_due_future'),
            'internal_team_id.required' => __('issues.errors.team_required'),
            'task_note.max' => __('issues.errors.text_max'),
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
