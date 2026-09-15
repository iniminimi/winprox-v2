<?php

namespace App\Data\Notifications;

final class WorkerNotificationPortalTarget
{
    public function __construct(
        public string $screen,
        public string $cursor,
    ) {}
}
