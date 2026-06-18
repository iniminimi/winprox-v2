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
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::Failed], true);
    }
}
