<?php

namespace App\Http\Requests\Public;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class CompletePortalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function ruleSet(): array
    {
        return [
            'completingNote' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
            'completingPhotos' => ['nullable', 'array', 'max:4'],
            'completingPhotos.*' => ['image', 'max:10240'],
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
            'completingNote.max' => __('portal.worker.errors.note_max'),
            'completingPhotos.max' => __('portal.report.errors.photos_max'),
            'completingPhotos.*.image' => __('portal.report.errors.photos_image'),
            'completingPhotos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
