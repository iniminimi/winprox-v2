<?php

namespace App\Actions\Team;

use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Worker;
use InvalidArgumentException;

class MarkWorkerLibraryViewedAction
{
    public const DOCUMENTS = 'documents';

    public const ANNOUNCEMENTS = 'announcements';

    public function handle(Worker $actor, int $tenantId, string $section): void
    {
        if ((int) $actor->tenant_id !== $tenantId || ! $actor->is_active) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        [$column, $latestVisibleId] = match ($section) {
            self::DOCUMENTS => [
                'last_team_document_id_seen',
                TeamDocument::query()->visibleToWorker($actor)->max('id'),
            ],
            self::ANNOUNCEMENTS => [
                'last_team_announcement_id_seen',
                TeamAnnouncement::query()->visibleToWorker($actor)->max('id'),
            ],
            default => throw new InvalidArgumentException('invalid_worker_library_section'),
        };

        if ($latestVisibleId !== null && (int) $latestVisibleId > (int) ($actor->{$column} ?? 0)) {
            $actor->update([$column => (int) $latestVisibleId]);
        }
    }
}
