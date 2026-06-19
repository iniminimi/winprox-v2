<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureDocumentTranslationSlotsAction;
use App\Models\Document;
use App\Support\Audit\AuditRecorder;

class ToggleLocationDocumentActiveAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureDocumentTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    public function handle(Document $document, ?int $actorUserId = null): void
    {
        $newStatus = ! (bool) $document->is_active;
        $document->is_active = $newStatus;
        if ($newStatus && $document->published_at === null) {
            $document->published_at = now();
        }
        $document->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $document->tenant_id,
            action: 'location_document.toggled',
            modelType: Document::class,
            modelId: (int) $document->id,
            payload: ['id' => $document->id, 'is_active' => $newStatus],
        );

        if ($newStatus) {
            $this->ensureTranslationSlots->handle($document->fresh());
        }
    }
}
