<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitPortalPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(int $slotsLeft): array
    {
        return [
            'newPortalPhotos' => ['nullable', 'array', 'max:'.$slotsLeft],
            'newPortalPhotos.*' => ['image', 'max:10240'],
        ];
    }

    public function rules(): array
    {
        return self::ruleSet(4);
    }

    public function messages(): array
    {
        return self::validationMessages();
    }

    /** @return array<string, string> */
    public static function validationMessages(): array
    {
        return [
            'newPortalPhotos.max' => __('portal.report.errors.photos_max'),
            'newPortalPhotos.*.image' => __('portal.report.errors.photos_image'),
            'newPortalPhotos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
