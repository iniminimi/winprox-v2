<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Support\Audit\AuditRecorder;

class ToggleIssueRecurrencePauseAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Issue $issue, ?int $actorUserId = null): Issue
    {
        if (! $issue->is_recurring) {
            return $issue;
        }

        $paused = $issue->recurrence_paused_at === null;
        $issue->recurrence_paused_at = $paused ? now() : null;
        $issue->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $issue->tenant_id,
            action: $paused ? 'issue.recurrence_paused' : 'issue.recurrence_resumed',
            modelType: Issue::class,
            modelId: (int) $issue->id,
            payload: ['id' => $issue->id, 'paused' => $paused],
        );

        return $issue->fresh();
    }
}
