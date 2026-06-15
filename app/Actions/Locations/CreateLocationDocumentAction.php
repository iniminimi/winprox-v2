<?php

namespace App\Actions\Locations;

use App\Models\Document;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Locations\StoredUploadMeta;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateLocationDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, unit_id: ?int, is_public: bool, requires_verification: bool, is_active: bool}  $data
     * @return array{document: Document, metadata_partial: bool}
     */
    public function handle(
        Location $location,
        TemporaryUploadedFile $file,
        array $data,
        int $tenantId,
        ?int $actorUserId = null,
    ): array {
        $unitId = $data['unit_id'] ?? null;
        if ($unitId !== null) {
            Unit::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $location->id)
                ->whereKey($unitId)
                ->firstOrFail();
        }

        Tenant::query()->findOrFail($tenantId)->assertCanAddDocument($unitId);

        $filePath = $file->store("location-documents/{$tenantId}/{$location->id}", 'public');
        $fileMeta = StoredUploadMeta::fromUpload($file, $filePath);
        $metadataPartial = $fileMeta['mime_type'] === null || $fileMeta['file_size_bytes'] === null;

        $generatedTitle = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $generatedTitle = trim($generatedTitle) !== '' ? $generatedTitle : __('locations.documents.default_title');

        $isActive = (bool) ($data['is_active'] ?? true);

        $document = Document::create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'unit_id' => $unitId,
            'title' => $generatedTitle,
            'description' => trim((string) $data['description']),
            'file_path' => $filePath,
            'mime_type' => $fileMeta['mime_type'],
            'file_size_bytes' => $fileMeta['file_size_bytes'],
            'is_public' => (bool) ($data['is_public'] ?? true),
            'requires_verification' => (bool) ($data['requires_verification'] ?? false),
            'is_active' => $isActive,
            'published_at' => $isActive ? now() : null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_document.created',
            modelType: Document::class,
            modelId: (int) $document->id,
            payload: ['id' => $document->id, 'location_id' => $location->id],
        );

        return ['document' => $document, 'metadata_partial' => $metadataPartial];
    }
}
