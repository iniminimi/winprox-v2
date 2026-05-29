<?php

namespace App\Actions\Issues;

use App\Events\Issues\IssueApproved;
use App\Models\Issue;
use App\Models\User;

class ApproveIssueAction
{
    /**
     * Keurt een melding goed: beschrijving + foto's worden zichtbaar (niet langer geblurd).
     * Tot dat moment toont de UI ze geblurd (moderatie van QR-inzendingen).
     */
    public function handle(Issue $issue, User $reviewer): Issue
    {
        $issue->forceFill([
            'approved_at' => now(),
            'approved_by' => $reviewer->getKey(),
        ])->save();

        event(new IssueApproved($issue->fresh()));

        return $issue->fresh();
    }
}
