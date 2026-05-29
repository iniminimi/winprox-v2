<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class ReportIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => __('portal.report.errors.description_required'),
            'description.min' => __('portal.report.errors.description_required'),
            'description.max' => __('portal.report.errors.description_max'),
            'photos.max' => __('portal.report.errors.photos_max'),
            'photos.*.image' => __('portal.report.errors.photos_image'),
            'photos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
