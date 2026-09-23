<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;

class ImportLocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return self::getReusableRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::getReusableMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function getReusableRules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getReusableMessages(): array
    {
        return [
            'file.required' => __('locations.locations_csv.errors.file_required'),
            'file.mimes' => __('locations.locations_csv.errors.unsupported_format'),
            'file.max' => __('locations.locations_csv.errors.file_too_large'),
        ];
    }
}
