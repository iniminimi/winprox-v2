<?php

namespace App\Http\Requests\Issues;

use App\Enums\RecurrenceIntervalUnit;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagerIssueStepOneRequest extends FormRequest
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
        $stopIds = $this->input('round_stop_unit_ids', []);
        $stopCount = is_array($stopIds)
            ? count(array_values(array_filter($stopIds, static fn ($id) => is_numeric($id))))
            : 0;
        $isInspectionRound = $this->boolean('is_recurring') && $stopCount >= 2;

        return self::ruleSet(
            $tenantId ? (int) $tenantId : null,
            $tenantId ? (bool) auth()->user()?->tenant?->hasEsgModule() : false,
            $isInspectionRound,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(
        ?int $tenantId = null,
        bool $esgModuleEnabled = false,
        bool $isInspectionRound = false,
    ): array {
        $rules = [
            'location_id' => $isInspectionRound
                ? ['nullable', 'integer', 'exists:locations,id']
                : ['required', 'integer', 'exists:locations,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'description' => ['required', 'string', 'min:3', 'max:'.TextDescriptionLimits::MAX],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_interval_value' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:1', 'max:24'],
            'recurrence_interval_unit' => ['nullable', 'required_if:is_recurring,true', Rule::enum(RecurrenceIntervalUnit::class)],
            'recurrence_lead_days' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:1', 'max:365'],
            'recurrence_first_due_date' => ['nullable', 'required_if:is_recurring,true', 'date', 'after_or_equal:today'],
            'round_stop_unit_ids' => [
                'exclude_unless:is_recurring,true',
                'array',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $count = count(array_values(array_filter(
                        $value,
                        static fn ($id) => is_numeric($id),
                    )));

                    // Optioneel op terugkerende melding; 1 stop is ongeldig, ≥2 = inspectieronde.
                    if ($count === 1) {
                        $fail(__('issues.errors.round_stops_min'));
                    }
                },
            ],
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
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:10240'],
        ];

        if (! $esgModuleEnabled || $tenantId === null) {
            $rules['esg_indicator_id'] = ['prohibited'];
        } else {
            $rules['esg_indicator_id'] = [
                'nullable',
                'prohibited_unless:is_recurring,true',
                'integer',
                Rule::exists('esg_indicators', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true),
                ),
            ];
            $rules['unit_id'][] = 'required_with:esg_indicator_id';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'location_id.required' => __('issues.errors.location_required'),
            'location_id.integer' => __('issues.errors.location_required'),
            'location_id.exists' => __('issues.errors.location_required'),
            'unit_id.exists' => __('issues.errors.unit_invalid'),
            'unit_id.required_with' => __('issues.errors.esg_indicator_requires_unit'),
            'description.required' => __('issues.errors.description_required'),
            'description.min' => __('issues.errors.description_min'),
            'description.max' => __('issues.errors.description_max'),
            'recurrence_interval_value.required_if' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_value.min' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_value.max' => __('issues.errors.recurrence_interval_required'),
            'recurrence_interval_unit.required_if' => __('issues.errors.recurrence_unit_required'),
            'recurrence_lead_days.required_if' => __('issues.errors.recurrence_lead_required'),
            'recurrence_lead_days.min' => __('issues.errors.recurrence_lead_required'),
            'recurrence_lead_days.max' => __('issues.errors.recurrence_lead_required'),
            'recurrence_first_due_date.required_if' => __('issues.errors.recurrence_due_required'),
            'recurrence_first_due_date.after_or_equal' => __('issues.errors.recurrence_due_future'),
            'round_stop_unit_ids.min' => __('issues.errors.round_stops_min'),
            'round_stop_unit_ids.*.exists' => __('issues.errors.round_stops_invalid'),
            'round_stop_unit_ids.*.integer' => __('issues.errors.round_stops_invalid'),
            'esg_indicator_id.exists' => __('issues.errors.esg_indicator_invalid'),
            'esg_indicator_id.prohibited_unless' => __('issues.errors.esg_indicator_recurring_only'),
            'photos.max' => __('issues.errors.photos_max'),
            'photos.*.image' => __('issues.errors.photos_image'),
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
