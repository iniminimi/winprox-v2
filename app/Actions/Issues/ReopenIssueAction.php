<?php

namespace App\Actions\Issues;

use App\Actions\Issues\AddIssueUpdateAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class ReopenIssueAction
{
    public function __construct(
        private AddIssueUpdateAction $addUpdate,
        private AuditRecorder $auditRecorder,
    ) {}

    /**
     * Heropen een gesloten melding.
     * Alleen beheerders kunnen dit doen.
     */
    public function handle(
        Issue $issue,
        User $actor,
        ?string $reason = null,
    ): Issue {
        // Alleen gesloten issues kunnen worden heropend
        if ($issue->status !== TaskStatus::Closed) {
            throw ValidationException::withMessages([
                'status' => [__('issues.errors.cannot_reopen_not_closed')],
            ]);
        }

        // Heropen de issue
        $issue->update(['status' => TaskStatus::New]);

        // Voeg update toe met reden
        if ($reason !== null && trim($reason) !== '') {
            $this->addUpdate->handle(
                issue: $issue,
                body: trim($reason),
                userId: $actor->id,
                kind: 'reopen_reason',
            );
        }

        // Audit logging
        $this->auditRecorder->record(
            userId: $actor->id,
            tenantId: $issue->tenant_id,
            action: 'issue.reopened',
            modelType: 'Issue',
            modelId: $issue->id,
            payload: [
                'reason' => trim($reason) ?? null,
            ],
        );

        return $issue->fresh();
    }
}
