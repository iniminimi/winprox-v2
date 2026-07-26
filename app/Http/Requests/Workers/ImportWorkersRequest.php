<?php

namespace App\Http\Requests\Workers;

use Illuminate\Foundation\Http\FormRequest;

class ImportWorkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Er moet een bestand worden geüpload.',
            'file.mimes' => 'Het bestand moet CSV of Excel (.xlsx) zijn.',
            'file.max' => 'Het bestand mag maximaal 10MB groot zijn.',
        ];
    }

    public static function getReusableRules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ];
    }

    public static function getReusableMessages(): array
    {
        return [
            'file.required' => 'Er moet een bestand worden geüpload.',
            'file.mimes' => 'Het bestand moet CSV of Excel (.xlsx) zijn.',
            'file.max' => 'Het bestand mag maximaal 10MB groot zijn.',
        ];
    }
}
