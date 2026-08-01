<?php

declare(strict_types=1);

namespace App\Http\Requests\Units;

use App\Enums\UnitCheckResult;
use App\Enums\UnitCheckSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordUnitCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createCheck', $this->route('unit'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::staticRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function staticRules(): array
    {
        return [
            'result' => ['required', 'string', Rule::in(UnitCheckResult::values())],
            'checked_at' => ['required', 'date'],
            'source' => ['sometimes', 'string', Rule::in(UnitCheckSource::values())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*' => ['string', 'max:200'],
        ];
    }

    /**
     * Portal Livewire property names.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function portalRuleSet(): array
    {
        return [
            'checkResult' => ['required', 'string', Rule::in(UnitCheckResult::values())],
            'checkLatitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:checkLongitude'],
            'checkLongitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:checkLatitude'],
            'checkCheckedAt' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'result.required' => __('unit_checks.validation.result_required'),
            'result.in' => __('unit_checks.validation.result_invalid'),
            'checked_at.required' => __('unit_checks.validation.checked_at_required'),
            'checked_at.date' => __('unit_checks.validation.checked_at_invalid'),
            'latitude.between' => __('qr.connect.gps_validation_between'),
            'longitude.between' => __('qr.connect.gps_validation_between'),
            'latitude.required_with' => __('qr.connect.gps_validation_required'),
            'longitude.required_with' => __('qr.connect.gps_validation_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function portalValidationMessages(): array
    {
        return [
            'checkResult.required' => __('portal.unit_check.errors.result_required'),
            'checkResult.in' => __('portal.unit_check.errors.result_invalid'),
            'checkCheckedAt.required' => __('portal.unit_check.errors.checked_at_required'),
            'checkCheckedAt.date' => __('portal.unit_check.errors.checked_at_invalid'),
            'checkLatitude.between' => __('qr.connect.gps_validation_between'),
            'checkLongitude.between' => __('qr.connect.gps_validation_between'),
            'checkLatitude.required_with' => __('qr.connect.gps_validation_required'),
            'checkLongitude.required_with' => __('qr.connect.gps_validation_required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $checkedAt = $this->input('checked_at');
            if (! is_string($checkedAt) || $checkedAt === '') {
                return;
            }

            self::assertCheckedAtIsReasonable($checkedAt, 'checked_at', $validator);
        });
    }

    public static function assertPortalCheckedAt(string $checkedAt, Validator $validator): void
    {
        self::assertCheckedAtIsReasonable(
            $checkedAt,
            'checkCheckedAt',
            $validator,
            __('portal.unit_check.errors.checked_at_invalid'),
        );
    }

    private static function assertCheckedAtIsReasonable(
        string $checkedAt,
        string $field,
        Validator $validator,
        ?string $message = null,
    ): void {
        try {
            $parsed = \Carbon\CarbonImmutable::parse($checkedAt);
        } catch (\Throwable) {
            return;
        }

        if ($parsed->greaterThan(now()->addMinutes(5))) {
            $validator->errors()->add(
                $field,
                $message ?? __('unit_checks.validation.checked_at_invalid'),
            );
        }
    }
}
