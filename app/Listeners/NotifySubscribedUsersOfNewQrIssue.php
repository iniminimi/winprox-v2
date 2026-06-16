<?php

namespace App\Listeners;

use App\Actions\Issues\NotifySubscribedUsersOfNewQrIssueAction;
use App\Events\Issues\IssueCreated;

class NotifySubscribedUsersOfNewQrIssue
{
    public function __construct(private NotifySubscribedUsersOfNewQrIssueAction $notify) {}

    public function handle(IssueCreated $event): void
    {
        $this->notify->handle($event->issue);
    }
}
