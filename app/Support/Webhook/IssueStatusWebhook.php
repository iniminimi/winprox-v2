<?php

namespace App\Support\Webhook;

use App\Enums\TaskStatus;
use App\Events\Issues\IssueStatusChanged;
use App\Models\Issue;

/**
 * Stuurt issue.status_changed alleen wanneer rollup de meldingstatus wijzigt.
 */
final class IssueStatusWebhook
{
    public static function dispatchIfChanged(Issue $issue, TaskStatus $before): void
    {
        $issue->refresh();

        if ($issue->status !== $before) {
            event(new IssueStatusChanged($issue, $before));
        }
    }
}
