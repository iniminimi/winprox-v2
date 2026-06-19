<?php

namespace App\Actions\Communication;

use App\Models\Document;
use App\Models\DocumentTranslation;

class BackfillDocumentTranslationSlotsAction
{
    public function __construct(private EnsureDocumentTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{documents: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $documentsProcessed = 0;
        $slotsCreated = 0;

        Document::query()
            ->where('is_active', true)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$documentsProcessed, &$slotsCreated): void {
                foreach ($documents as $document) {
                    $before = DocumentTranslation::query()
                        ->where('document_id', $document->id)
                        ->count();

                    $this->ensureSlots->handle($document);

                    $after = DocumentTranslation::query()
                        ->where('document_id', $document->id)
                        ->count();

                    $documentsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'documents' => $documentsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
