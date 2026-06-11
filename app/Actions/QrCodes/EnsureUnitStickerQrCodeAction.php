<?php

namespace App\Actions\QrCodes;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Ensure a unit has a QrCode record for sticker export (number + qr.scan URL).
 */
class EnsureUnitStickerQrCodeAction
{
    public function __construct(
        private GenerateQrStickerNumberAction $generateStickerNumber,
        private AuditRecorder $audit,
    ) {}

    public function handle(Unit $unit, int $tenantId, ?int $actorUserId = null): QrCode
    {
        if ((int) $unit->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Unit must belong to the tenant.');
        }

        $unit->loadMissing('qrCodes');

        $existing = $unit->qrCodes
            ->sortBy(fn (QrCode $qr) => $qr->id)
            ->first();

        if ($existing instanceof QrCode) {
            return $existing;
        }

        $qrCode = QrCode::create([
            'tenant_id' => $tenantId,
            'unit_id' => $unit->id,
            'status' => QrCodeStatus::Active,
            'sticker_number' => $this->generateStickerNumber->handle(),
            'linked_at' => now(),
            'linked_by' => $actorUserId,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'qr_code.sticker_export_provisioned',
            modelType: QrCode::class,
            modelId: (int) $qrCode->id,
            payload: [
                'qr_code_id' => $qrCode->id,
                'sticker_number' => $qrCode->sticker_number,
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
            ],
        );

        return $qrCode;
    }
}
