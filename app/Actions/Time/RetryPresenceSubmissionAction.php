<?php

namespace App\Actions\Time;

use App\Enums\PresenceSubmissionStatus;
use App\Jobs\SubmitPresenceSubmissionJob;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class RetryPresenceSubmissionAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(PresenceSubmission $submission, ?int $actorUserId = null): PresenceSubmission
    {
        $tenant = Tenant::query()->find($submission->tenant_id);
        if ($tenant === null || ! TimeModuleAccess::tenantHasModule($tenant)) {
            throw new InvalidArgumentException('time_module_disabled');
        }

        if (! $tenant->presenceComplianceEnabled()) {
            throw new InvalidArgumentException('presence_compliance_disabled');
        }

        if (! in_array($submission->status, [
            PresenceSubmissionStatus::Failed,
            PresenceSubmissionStatus::Skipped,
            PresenceSubmissionStatus::Pending,
        ], true)) {
            throw new InvalidArgumentException('presence_submission_not_retryable');
        }

        if ($submission->status !== PresenceSubmissionStatus::Pending) {
            $submission->update([
                'status' => PresenceSubmissionStatus::Pending,
                'error_message' => null,
                'rsz_id' => null,
                'rsz_validity' => null,
                'remarks' => null,
                'request_meta' => null,
                'submitted_at' => null,
            ]);
        }

        $fresh = $submission->fresh();

        SubmitPresenceSubmissionJob::dispatch((int) $fresh->id);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'presence_submission.retried',
            modelType: PresenceSubmission::class,
            modelId: (int) $fresh->id,
            payload: [
                'source_event' => $fresh->source_event->value,
                'presence_type' => $fresh->presence_type->value,
            ],
        );

        return $fresh;
    }
}
