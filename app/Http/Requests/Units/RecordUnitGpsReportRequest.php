<?php

declare(strict_types=1);

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RecordUnitGpsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateGps', $this->route('unit'));
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'reported_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function portalRuleSet(): array
    {
        return [
            'gpsLatitude' => ['required', 'numeric', 'between:-90,90'],
            'gpsLongitude' => ['required', 'numeric', 'between:-180,180'],
            'gpsReportedAt' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'latitude.required' => __('qr.connect.gps_validation_required'),
            'latitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'latitude.between' => __('qr.connect.gps_validation_between'),
            'longitude.required' => __('qr.connect.gps_validation_required'),
            'longitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'longitude.between' => __('qr.connect.gps_validation_between'),
            'reported_at.required' => __('qr.connect.gps_validation_required'),
            'reported_at.date' => __('qr.connect.gps_validation_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function portalValidationMessages(): array
    {
        return [
            'gpsLatitude.required' => __('qr.connect.gps_validation_required'),
            'gpsLatitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'gpsLatitude.between' => __('qr.connect.gps_validation_between'),
            'gpsLongitude.required' => __('qr.connect.gps_validation_required'),
            'gpsLongitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'gpsLongitude.between' => __('qr.connect.gps_validation_between'),
            'gpsReportedAt.required' => __('qr.connect.gps_validation_required'),
            'gpsReportedAt.date' => __('qr.connect.gps_validation_required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $reportedAt = $this->input('reported_at');
            if (! is_string($reportedAt) || $reportedAt === '') {
                return;
            }

            self::assertReportedAtIsReasonable($reportedAt, 'reported_at', $validator);
        });
    }

    public static function assertPortalReportedAt(string $reportedAt, Validator $validator): void
    {
        self::assertReportedAtIsReasonable($reportedAt, 'gpsReportedAt', $validator);
    }

    private static function assertReportedAtIsReasonable(string $reportedAt, string $field, Validator $validator): void
    {
        try {
            $parsed = \Carbon\CarbonImmutable::parse($reportedAt);
        } catch (\Throwable) {
            return;
        }

        if ($parsed->greaterThan(now()->addMinutes(5))) {
            $validator->errors()->add($field, __('qr.connect.gps_validation_required'));
        }
    }
}
