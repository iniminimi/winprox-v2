<?php

namespace App\Http\Requests\Issues;

use App\Enums\RecurrenceIntervalUnit;
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
        return self::ruleSet();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_interval_value' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:1', 'max:24'],
            'recurrence_interval_unit' => ['nullable', 'required_if:is_recurring,true', Rule::enum(RecurrenceIntervalUnit::class)],
            'recurrence_lead_days' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:1', 'max:365'],
            'recurrence_first_due_date' => ['nullable', 'required_if:is_recurring,true', 'date', 'after_or_equal:today'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => __('issues.errors.description_required'),
            'description.min' => __('issues.errors.description_min'),
            'description.max' => __('issues.errors.description_max'),
            'photos.max' => __('issues.errors.photos_max'),
            'photos.*.image' => __('issues.errors.photos_image'),
        ];
    }
}
