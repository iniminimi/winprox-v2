<?php

namespace App\Http\Requests\Issues;

use App\Enums\TaskPriority;
use App\Http\Requests\Tasks\AssignedWorkerRules;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class AssignIssueTeamTaskRequest extends FormRequest
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
        $tenantId = auth()->user()?->tenant_id;

        return self::ruleSet(
            $tenantId ? (int) $tenantId : null,
            $this->integer('internal_team_id') ?: null,
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(?int $tenantId = null, ?int $teamId = null): array
    {
        return [
            'internal_team_id' => ['required', 'integer', 'exists:internal_teams,id'],
            'assigned_worker_id' => AssignedWorkerRules::forTeamId($teamId, $tenantId),
            'task_note' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
            'task_priority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return array_merge([
            'internal_team_id.required' => __('issues.errors.team_required'),
            'internal_team_id.integer' => __('issues.errors.team_required'),
            'internal_team_id.exists' => __('issues.errors.team_required'),
            'task_note.max' => __('issues.errors.text_max'),
        ], AssignedWorkerRules::messages());
    }
}
