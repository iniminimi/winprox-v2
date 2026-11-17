<?php

namespace App\Support\Push;

final class WorkerPushScreen
{
    public const SCHEDULE = 'schedule';

    public const DOCUMENTS = 'documents';

    public const ANNOUNCEMENTS = 'announcements';

    public const ABSENCE = 'absence';

    public static function isOpen(string $screen): bool
    {
        return in_array($screen, [
            self::SCHEDULE,
            self::DOCUMENTS,
            self::ANNOUNCEMENTS,
            self::ABSENCE,
        ], true);
    }
}
