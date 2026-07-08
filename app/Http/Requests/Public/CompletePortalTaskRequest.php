<?php

namespace App\Http\Requests\Public;

use App\Enums\EsgIndicatorType;
use App\Http\Requests\Esg\RecordEsgMeasurementRequest;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class CompletePortalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function ruleSet(?EsgIndicatorType $esgType = null): array
    {
        $rules = [
            'completingNote' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
            'completingPhotos' => ['nullable', 'array', 'max:4'],
            'completingPhotos.*' => ['image', 'max:10240'],
        ];

        if ($esgType !== null) {
            $rules = array_merge($rules, RecordEsgMeasurementRequest::portalRuleSet($esgType));
        }

        return $rules;
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
    public static function validationMessages(?EsgIndicatorType $esgType = null): array
    {
        $messages = [
            'completingNote.max' => __('portal.worker.errors.note_max'),
            'completingPhotos.max' => __('portal.report.errors.photos_max'),
            'completingPhotos.*.image' => __('portal.report.errors.photos_image'),
            'completingPhotos.*.max' => __('portal.report.errors.photos_size'),
        ];

        if ($esgType !== null) {
            $messages = array_merge(
                $messages,
                RecordEsgMeasurementRequest::portalValidationMessages($esgType),
            );
        }

        return $messages;
    }
}
