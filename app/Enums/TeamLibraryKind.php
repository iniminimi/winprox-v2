<?php

namespace App\Enums;

use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;

enum TeamLibraryKind: string
{
    case Documents = 'documents';
    case Announcements = 'announcements';

    /**
     * @return class-string<TeamDocument|TeamAnnouncement>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Documents => TeamDocument::class,
            self::Announcements => TeamAnnouncement::class,
        };
    }
}
