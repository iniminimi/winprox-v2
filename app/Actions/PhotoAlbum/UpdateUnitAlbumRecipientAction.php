<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\UnitAlbumRecipient;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

final class UpdateUnitAlbumRecipientAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function findForUnit(int $recipientId, int $tenantId, int $unitId): ?UnitAlbumRecipient
    {
        return UnitAlbumRecipient::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unitId)
            ->find($recipientId);
    }

    public function handle(UnitAlbumRecipient $recipient, ?string $description, int $tenantId, int $actorUserId): UnitAlbumRecipient
    {
        if ((int) $recipient->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $description = trim((string) $description);
        $recipient->description = $description !== '' ? $description : null;
        $recipient->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit_album.recipient_updated',
            modelType: UnitAlbumRecipient::class,
            modelId: (int) $recipient->id,
            payload: ['unit_id' => (int) $recipient->unit_id],
        );

        return $recipient;
    }
}
