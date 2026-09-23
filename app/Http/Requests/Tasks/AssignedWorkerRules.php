<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Validation\Rule;

/**
 * Herbruikbare regels: optionele worker moet actief zijn en tot het gekozen team horen.
 */
final class AssignedWorkerRules
{
    /**
     * @return array<int, mixed>
     */
    public static function forTeamId(?int $teamId, ?int $tenantId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('workers', 'id')->when(
                $tenantId !== null && $teamId !== null && $teamId > 0,
                fn ($rule) => $rule
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->where('internal_team_id', $teamId),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'assigned_worker_id'): array
    {
        return [
            "{$attribute}.exists" => __('tasks.errors.worker_not_on_team'),
            "{$attribute}.integer" => __('tasks.errors.worker_not_on_team'),
        ];
    }
}
