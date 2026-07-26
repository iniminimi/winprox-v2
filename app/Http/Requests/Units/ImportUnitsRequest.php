<?php

namespace App\Http\Requests\Units;

use Illuminate\Foundation\Http\FormRequest;

class ImportUnitsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
            'location_id' => 'required|integer|exists:locations,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Er moet een bestand worden geüpload.',
            'file.mimes' => 'Het bestand moet een CSV-bestand zijn.',
            'file.max' => 'Het bestand mag maximaal 10MB groot zijn.',
            'location_id.required' => 'Een locatie is verplicht.',
            'location_id.exists' => 'Locatie niet gevonden.',
        ];
    }

    /**
     * Get reusable validation rules for non-HTTP contexts.
     */
    public static function getReusableRules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ];
    }

    /**
     * Get reusable validation messages for non-HTTP contexts.
     */
    public static function getReusableMessages(): array
    {
        return [
            'file.required' => 'Er moet een bestand worden geüpload.',
            'file.mimes' => 'Het bestand moet een CSV-bestand zijn.',
            'file.max' => 'Het bestand mag maximaal 10MB groot zijn.',
        ];
    }
}
