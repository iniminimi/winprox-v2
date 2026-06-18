<?php

namespace App\Http\Requests\Issues;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class CreateIssueRequest extends FormRequest
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
        return [
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:'.TextDescriptionLimits::MAX],
            'team_ids' => ['array'],
            'team_ids.*' => ['integer', 'exists:internal_teams,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => __('issues.errors.description_required'),
            'description.max' => __('issues.errors.description_max'),
        ];
    }
}
