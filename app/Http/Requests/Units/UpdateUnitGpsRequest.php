<?php

declare(strict_types=1);

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitGpsRequest extends FormRequest
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
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => __('qr.connect.gps_validation_required'),
            'latitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'latitude.between' => __('qr.connect.gps_validation_between'),
            'longitude.required' => __('qr.connect.gps_validation_required'),
            'longitude.numeric' => __('qr.connect.gps_validation_numeric'),
            'longitude.between' => __('qr.connect.gps_validation_between'),
        ];
    }

    /**
     * Static rules for non-HTTP contexts (Livewire, API, etc.)
     *
     * @return array<string, array<int, mixed>>
     */
    public static function staticRules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
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
        ];
    }
}
