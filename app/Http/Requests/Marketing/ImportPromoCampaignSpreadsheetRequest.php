<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class ImportPromoCampaignSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleSet(): array
    {
        return [
            'spreadsheet' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'columnMapping' => ['required', 'array'],
            'columnMapping.name' => ['required', 'string', 'max:255'],
            'columnMapping.email' => ['nullable', 'string', 'max:255'],
            'columnMapping.street_address' => ['nullable', 'string', 'max:255'],
            'columnMapping.postal_code' => ['nullable', 'string', 'max:255'],
            'columnMapping.city' => ['nullable', 'string', 'max:255'],
        ];
    }
}
