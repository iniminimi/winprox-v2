<?php

namespace App\Actions\Locations;

use App\Models\Document;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteLocationDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Document $document, ?int $actorUserId = null): void
    {
        if ($document->file_path !== '' && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $tenantId = (int) $document->tenant_id;
        $documentId = (int) $document->id;
        $locationId = (int) $document->location_id;

        $document->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_document.deleted',
            modelType: Document::class,
            modelId: $documentId,
            payload: ['id' => $documentId, 'location_id' => $locationId],
        );
    }
}
