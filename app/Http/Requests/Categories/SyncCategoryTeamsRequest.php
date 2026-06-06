<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCategoryTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'teams' => ['required', 'array', 'min:1'],
            'teams.*' => ['integer', 'exists:internal_teams,id'],
        ];
    }
}
