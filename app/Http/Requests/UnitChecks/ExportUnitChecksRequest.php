<?php

declare(strict_types=1);

namespace App\Http\Requests\UnitChecks;

use App\Enums\UnitCheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportUnitChecksRequest extends FormRequest
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
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'result' => ['nullable', 'string', Rule::in(['all', ...UnitCheckResult::values()])],
            'location' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
