<?php

namespace App\Actions\Issues;

use App\Actions\Issues\AddIssueUpdateAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class CloseIssueAction
{
    public function __construct(
        private AddIssueUpdateAction $addUpdate,
        private AuditRecorder $auditRecorder,
    ) {}

    /**
     * Sluit een melding zonder goedkeuring (bijv. malafide melding).
     * Alle taken worden ook gesloten.
     */
    public function handle(
        Issue $issue,
        ?User $actor = null,
        ?string $reason = null,
    ): Issue {
        // Alleen ongekeurde issues kunnen worden gesloten zonder goedkeuring
        if ($issue->isApproved()) {
            throw ValidationException::withMessages([
                'status' => [__('issues.errors.cannot_close_approved')],
            ]);
        }

        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => [__('issues.errors.close_reason_required')],
            ]);
        }

        // Sluit alle taken
        foreach ($issue->tasks as $task) {
            if ($task->status !== TaskStatus::Closed) {
                $task->update([
                    'status' => TaskStatus::Closed,
                    'completed_at' => $task->completed_at ?? now(),
                ]);
            }
        }

        // Sluit de issue
        $issue->update(['status' => TaskStatus::Closed]);

        // Voeg update toe met reden
        $this->addUpdate->handle(
            issue: $issue,
            body: trim($reason),
            userId: $actor?->id,
            kind: 'close_reason',
        );

        // Audit logging
        $this->auditRecorder->record(
            action: 'issue.closed',
            model: $issue,
            actorUserId: $actor?->id,
            tenantId: $issue->tenant_id,
            payload: [
                'reason' => trim($reason),
                'tasks_closed' => $issue->tasks->count(),
            ],
        );

        return $issue->fresh();
    }
}
