<?php

namespace App\Actions\Team;

use App\Models\TeamDocument;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UpdateTeamDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, worker_id: ?int, is_active: bool}  $data
     */
    public function handle(
        TeamDocument $document,
        array $data,
        ?TemporaryUploadedFile $file,
        int $tenantId,
        ?int $actorUserId = null,
    ): TeamDocument {
        $workerId = $data['worker_id'] ?? null;
        if ($workerId !== null) {
            Worker::query()
                ->where('tenant_id', $tenantId)
                ->where('internal_team_id', $document->internal_team_id)
                ->whereKey($workerId)
                ->firstOrFail();
        }

        $isActive = (bool) ($data['is_active'] ?? true);
        $updates = [
            'description' => trim((string) $data['description']),
            'worker_id' => $workerId,
            'is_active' => $isActive,
            'published_at' => $isActive ? ($document->published_at ?? now()) : null,
        ];

        if ($file !== null) {
            $document->deleteFile();
            $path = $file->store('team-documents/'.$tenantId, TeamDocument::DISK);
            $title = trim(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));
            $updates['file_path'] = $path;
            $updates['title'] = $title !== '' ? $title : $document->title;
            $updates['mime_type'] = $file->getMimeType() ?: $file->getClientMimeType();
            $updates['file_size_bytes'] = $file->getSize() ?: null;
        }

        $document->update($updates);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_document.updated',
            modelType: TeamDocument::class,
            modelId: (int) $document->id,
            payload: ['id' => $document->id, 'worker_id' => $workerId],
        );

        return $document->fresh();
    }
}
