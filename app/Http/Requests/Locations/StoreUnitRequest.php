<?php

namespace App\Http\Requests\Locations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $locationId = null, ?int $ignoreUnitId = null): array
    {
        $unique = Rule::unique('units', 'name')
            ->where(fn ($q) => $q->where('location_id', $locationId));

        if ($ignoreUnitId !== null) {
            $unique->ignore($ignoreUnitId);
        }

        return [
            'name' => ['required', 'string', 'min:1', 'max:255', $unique],
            'default_internal_team_id' => ['nullable', 'integer', 'exists:internal_teams,id'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $locationId = $this->route('location')?->id ?? $this->input('location_id');

        return self::ruleSet($locationId ? (int) $locationId : null);
    }
}
