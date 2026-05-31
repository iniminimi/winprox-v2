<?php

namespace App\Http\Requests\Issues;

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
        return self::ruleSet();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'internal_team_id' => ['required', 'integer', 'exists:internal_teams,id'],
            'task_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageSet(): array
    {
        return [
            'internal_team_id.required' => __('issues.errors.team_required'),
            'internal_team_id.integer' => __('issues.errors.team_required'),
            'internal_team_id.exists' => __('issues.errors.team_required'),
        ];
    }
}
