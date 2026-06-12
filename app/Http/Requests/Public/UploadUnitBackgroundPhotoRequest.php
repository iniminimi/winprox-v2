<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class UploadUnitBackgroundPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function ruleSet(): array
    {
        return [
            'backgroundPhoto' => ['required', 'array', 'max:1'],
            'backgroundPhoto.0' => ['image', 'max:10240'],
        ];
    }

    public function rules(): array
    {
        return self::ruleSet();
    }

    public function messages(): array
    {
        return self::validationMessages();
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'backgroundPhoto.max' => __('portal.report.errors.photos_max'),
            'backgroundPhoto.0.image' => __('portal.report.errors.photos_image'),
            'backgroundPhoto.0.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
