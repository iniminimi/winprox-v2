<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\UnitAlbumRecipient;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

final class DeleteUnitAlbumRecipientAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handleId(int $recipientId, int $tenantId, int $actorUserId): void
    {
        $recipient = UnitAlbumRecipient::query()->where('tenant_id', $tenantId)->find($recipientId);
        if ($recipient === null) {
            return;
        }

        $this->handle($recipient, $tenantId, $actorUserId);
    }

    public function handle(UnitAlbumRecipient $recipient, int $tenantId, int $actorUserId): void
    {
        if ((int) $recipient->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $recipientId = (int) $recipient->id;
        $unitId = (int) $recipient->unit_id;
        $recipient->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit_album.recipient_deleted',
            modelType: UnitAlbumRecipient::class,
            modelId: $recipientId,
            payload: ['unit_id' => $unitId],
        );
    }
}
