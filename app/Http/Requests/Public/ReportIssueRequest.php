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
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'photos' => ['array', 'max:4'],
            'photos.*' => ['image', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => __('report.errors.description_required'),
            'description.max' => __('report.errors.description_max'),
            'photos.max' => __('report.errors.photos_max'),
            'photos.*.image' => __('report.errors.photos_image'),
            'photos.*.max' => __('report.errors.photos_size'),
        ];
    }
}
