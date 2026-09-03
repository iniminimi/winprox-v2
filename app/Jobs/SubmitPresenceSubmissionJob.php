<?php

namespace App\Jobs;

use App\Actions\Time\SubmitPresenceBatchAction;
use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitPresenceSubmissionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $presenceSubmissionId) {}

    public function handle(SubmitPresenceBatchAction $submit): void
    {
        $submission = PresenceSubmission::query()->find($this->presenceSubmissionId);
        if ($submission === null) {
            return;
        }

        if ($submission->status !== PresenceSubmissionStatus::Pending) {
            return;
        }

        $submit->handle($submission);
    }
}
