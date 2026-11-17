<?php

namespace App\Actions\Team;

use App\Actions\Notifications\CreateNotificationAction;
use App\Actions\Notifications\DispatchWorkerPushAction;
use App\Enums\WorkerNotificationType;
use App\Models\TeamAnnouncement;
use App\Models\TeamDocument;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Push\WorkerPushScreen;

class NotifyWorkersOfVisibleLibraryAction
{
    public function __construct(
        private CreateNotificationAction $createNotification,
        private DispatchWorkerPushAction $dispatchPush,
    ) {}

    public function handleDocument(TeamDocument $document): void
    {
        if (! $document->is_active) {
            return;
        }

        $this->notify(
            (int) $document->tenant_id,
            (int) $document->internal_team_id,
            $document->worker_id !== null ? (int) $document->worker_id : null,
            WorkerNotificationType::TeamDocument,
            (string) $document->id,
            WorkerPushScreen::DOCUMENTS,
            'time.portal.push.document_title',
            'time.portal.push.document_body',
            'team_document:'.$document->id,
        );
    }

    public function handleAnnouncement(TeamAnnouncement $announcement): void
    {
        if (! $announcement->is_active || $announcement->isExpired()) {
            return;
        }

        $this->notify(
            (int) $announcement->tenant_id,
            (int) $announcement->internal_team_id,
            $announcement->worker_id !== null ? (int) $announcement->worker_id : null,
            WorkerNotificationType::TeamAnnouncement,
            (string) $announcement->id,
            WorkerPushScreen::ANNOUNCEMENTS,
            'time.portal.push.announcement_title',
            'time.portal.push.announcement_body',
            'team_announcement:'.$announcement->id,
        );
    }

    private function notify(
        int $tenantId,
        int $teamId,
        ?int $workerId,
        WorkerNotificationType $type,
        string $referenceId,
        string $screen,
        string $titleKey,
        string $bodyKey,
        string $tag,
    ): void {
        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant instanceof Tenant) {
            return;
        }

        $workers = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('internal_team_id', $teamId)
            ->where('is_active', true)
            ->when($workerId !== null, fn ($query) => $query->whereKey($workerId))
            ->get();

        foreach ($workers as $worker) {
            $row = $this->createNotification->recordOnce($tenant, $worker, $type, $referenceId);
            if (! $row->wasRecentlyCreated) {
                continue;
            }

            $this->dispatchPush->handle($tenant, $worker, $titleKey, $bodyKey, [], $screen, $tag);
        }
    }
}
