<?php

namespace App\Actions\Team;

use App\Models\InternalTeam;
use App\Models\TeamDocument;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateTeamDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, worker_id: ?int, is_active: bool, original_language?: ?string}  $data
     */
    public function handle(
        InternalTeam $team,
        TemporaryUploadedFile $file,
        array $data,
        int $tenantId,
        ?int $actorUserId = null,
    ): TeamDocument {
        $workerId = $this->workerOnTeam($team, $data['worker_id'] ?? null, $tenantId);

        $filePath = $file->store('team-documents/'.$tenantId, TeamDocument::DISK);
        $title = trim(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME));
        $title = mb_substr($title !== '' ? $title : __('team.library.default_title'), 0, 255);
        $isActive = (bool) ($data['is_active'] ?? true);

        $document = TeamDocument::create([
            'tenant_id' => $tenantId,
            'internal_team_id' => $team->id,
            'worker_id' => $workerId,
            'title' => $title,
            'description' => trim((string) $data['description']),
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize() ?: null,
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'team_document.created',
            modelType: TeamDocument::class,
            modelId: (int) $document->id,
            payload: [
                'id' => $document->id,
                'internal_team_id' => $team->id,
                'worker_id' => $workerId,
            ],
        );

        return $document;
    }

    private function workerOnTeam(InternalTeam $team, ?int $workerId, int $tenantId): ?int
    {
        if ($workerId === null) {
            return null;
        }

        Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('internal_team_id', $team->id)
            ->whereKey($workerId)
            ->firstOrFail();

        return $workerId;
    }
}
