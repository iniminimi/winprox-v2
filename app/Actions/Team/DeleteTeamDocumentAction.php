<?php

namespace App\Actions\Team;

use App\Models\TeamDocument;
use App\Support\Audit\AuditRecorder;

class DeleteTeamDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(TeamDocument $document, int $tenantId, ?int $actorUserId = null): void
    {
        $id = (int) $document->id;
        $document->deleteFile();
        $document->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_document.deleted',
            modelType: TeamDocument::class,
            modelId: $id,
            payload: ['id' => $id],
        );
    }
}
