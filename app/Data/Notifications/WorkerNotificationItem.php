<?php

namespace App\Data\Notifications;

use App\Enums\WorkerNotificationType;
use Carbon\Carbon;

final class WorkerNotificationItem
{
    public function __construct(
        public int $id,
        public WorkerNotificationType $type,
        public string $referenceId,
        public ?Carbon $readAt,
        public Carbon $updatedAt,
        public WorkerNotificationPortalTarget $target,
    ) {}

    public function isUnread(): bool
    {
        return $this->readAt === null;
    }
}
