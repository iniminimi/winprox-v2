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

class UpdateLocationDocumentAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{description: string, unit_id: ?int, is_public: bool, requires_verification: bool, is_active: bool}  $data
     * @return array{metadata_partial: bool}
     */
    public function handle(
        Location $location,
        Document $document,
        array $data,
        ?TemporaryUploadedFile $replacementFile,
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

        $tenant = Tenant::query()->findOrFail($tenantId);
        if ($unitId !== null && (int) $document->unit_id !== (int) $unitId) {
            $tenant->assertCanAssignDocumentToUnit($unitId, (int) $document->id);
        }

        $isActive = (bool) ($data['is_active'] ?? true);
        $updatePayload = [
            'description' => trim((string) $data['description']),
            'unit_id' => $unitId,
            'is_public' => (bool) ($data['is_public'] ?? true),
            'requires_verification' => (bool) ($data['requires_verification'] ?? false),
            'is_active' => $isActive,
            'published_at' => $isActive ? ($document->published_at ?? now()) : $document->published_at,
        ];

        $metadataPartial = false;

        if ($replacementFile instanceof TemporaryUploadedFile) {
            $newPath = $replacementFile->store("location-documents/{$tenantId}/{$location->id}", 'public');
            $fileMeta = StoredUploadMeta::fromUpload($replacementFile, $newPath);
            $metadataPartial = $fileMeta['mime_type'] === null || $fileMeta['file_size_bytes'] === null;

            if ($document->file_path !== '' && Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $updatePayload['file_path'] = $newPath;
            $updatePayload['mime_type'] = $fileMeta['mime_type'];
            $updatePayload['file_size_bytes'] = $fileMeta['file_size_bytes'];
        }

        $document->update($updatePayload);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location_document.updated',
            modelType: Document::class,
            modelId: (int) $document->id,
            payload: ['id' => $document->id, 'location_id' => $location->id],
        );

        return ['metadata_partial' => $metadataPartial];
    }
}
