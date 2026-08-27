<?php

declare(strict_types=1);

namespace App\Http\Requests\Issues;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportIssuesRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::enum(TaskStatus::class)],
            'team' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:200'],
            'recurring' => ['nullable'],
            'inspection_round' => ['nullable'],
            'unit_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
