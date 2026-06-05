<?php

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\QrLinkPhoto;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\IssuePhotoStorage;
use Illuminate\Http\UploadedFile;

class LinkQrCodeToUnitAction
{
    public function __construct(
        private AuditRecorder $audit,
        private IssuePhotoStorage $storage,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(QrCode $qrCode, Unit $unit, int $tenantId, ?int $actorUserId = null, array $photos = []): QrCode
    {
        // Verify tenant ownership
        if ($qrCode->tenant_id !== $tenantId || $unit->tenant_id !== $tenantId) {
            throw new \InvalidArgumentException('QR code and unit must belong to the same tenant');
        }

        // Verify QR code can be linked
        if (!$qrCode->canBeLinked()) {
            throw new \InvalidArgumentException('QR code cannot be linked in its current state');
        }

        // Prevent race condition - check if already linked
        if ($qrCode->unit_id !== null) {
            throw new \InvalidArgumentException('QR code was already linked by another user');
        }

        $qrCode->update([
            'unit_id' => $unit->id,
            'status' => QrCodeStatus::Active,
            'linked_at' => now(),
            'linked_by' => $actorUserId,
        ]);

        $storedPhotoCount = 0;
        foreach ($photos as $photo) {
            if ($photo instanceof UploadedFile) {
                QrLinkPhoto::create([
                    'tenant_id' => $tenantId,
                    'qr_code_id' => $qrCode->id,
                    'unit_id' => $unit->id,
                    'path' => $this->storage->storePrecompressedCopy($photo),
                ]);
                $storedPhotoCount++;
            }
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'qr_code.linked',
            modelType: QrCode::class,
            modelId: (int) $qrCode->id,
            payload: [
                'qr_code_id' => $qrCode->id,
                'sticker_number' => $qrCode->sticker_number,
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'photo_count' => $storedPhotoCount,
            ],
        );

        return $qrCode->fresh();
    }
}
