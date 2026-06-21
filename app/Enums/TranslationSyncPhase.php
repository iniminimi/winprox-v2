<?php

namespace App\Enums;

enum TranslationSyncPhase: string
{
    case Queued = 'queued';
    case ExportingRemote = 'exporting_remote';
    case Downloading = 'downloading';
    case Translating = 'translating';
    case Uploading = 'uploading';
    case ImportingRemote = 'importing_remote';
    case Cancelling = 'cancelling';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled, self::Failed], true);
    }
}
