<?php

namespace App\Actions\QrCodes;

use App\Models\QrCode;
use App\Models\QrLinkPhoto;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

class StoreQrLinkPhotosAction
{
    public function __construct(
        private AuditRecorder $audit,
        private IssuePhotoStorage $storage,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(
        Unit $unit,
        QrCode $qrCode,
        array $photos,
        ?int $actorUserId = null,
    ): int {
        $storedCount = 0;

        foreach ($photos as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            QrLinkPhoto::create([
                'tenant_id' => (int) $unit->tenant_id,
                'qr_code_id' => (int) $qrCode->id,
                'unit_id' => (int) $unit->id,
                'path' => $this->storage->storePrecompressedCopy($photo),
            ]);

            $storedCount++;
        }

        if ($storedCount > 0) {
            $this->audit->record(
                userId: $actorUserId,
                tenantId: (int) $unit->tenant_id,
                action: 'qr_link_photo.stored',
                modelType: Unit::class,
                modelId: (int) $unit->id,
                payload: [
                    'unit_id' => $unit->id,
                    'qr_code_id' => $qrCode->id,
                    'photo_count' => $storedCount,
                ],
            );
        }

        return $storedCount;
    }
}
