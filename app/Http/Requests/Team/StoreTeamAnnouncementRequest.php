<?php

namespace App\Http\Requests\Team;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamAnnouncementRequest extends FormRequest
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
        return self::fieldRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'description' => ['required', 'string', 'min:3', 'max:'.TextDescriptionLimits::MAX],
            'audience' => ['required', 'in:team,worker'],
            'workerId' => ['nullable', 'integer'],
            'isActive' => ['boolean'],
            'expiresAt' => ['nullable', 'date'],
        ];
    }
}
