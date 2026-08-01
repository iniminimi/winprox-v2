<?php

declare(strict_types=1);

namespace App\Http\Requests\Units;

use App\Enums\UnitCheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IngestUnitCheckByExternalIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'external_unit_id' => ['required', 'string', 'max:100'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'result' => ['required', 'string', Rule::in(UnitCheckResult::values())],
            'checked_at' => ['required', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*' => ['string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(RecordUnitCheckRequest::validationMessages(), [
            'external_unit_id.required' => __('unit_checks.validation.external_unit_id_required'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkedAt = $this->input('checked_at');
            if (! is_string($checkedAt) || $checkedAt === '') {
                return;
            }

            RecordUnitCheckRequest::assertApiCheckedAt($checkedAt, $validator);
        });
    }
}
