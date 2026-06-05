<?php

namespace App\Actions\QrCodes;

use App\Models\QrLinkPhoto;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteQrLinkPhotoAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(QrLinkPhoto $photo, ?int $actorUserId = null): void
    {
        $tenantId = (int) $photo->tenant_id;
        $qrCodeId = (int) $photo->qr_code_id;
        $unitId = (int) $photo->unit_id;
        $path = $photo->path;

        if ($photo->hasPublicFile()) {
            Storage::disk('public')->delete($path);
        }

        $photo->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'qr_link_photo.deleted',
            modelType: QrLinkPhoto::class,
            modelId: (int) $photo->id,
            payload: [
                'qr_code_id' => $qrCodeId,
                'unit_id' => $unitId,
                'path' => $path,
            ],
        );
    }
}
