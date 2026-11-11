<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\UnitAlbumPhoto;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\UnitAlbumPhotoStorage;
use InvalidArgumentException;

/**
 * Wist een albumfoto (bestand + rij). Een uitvoerder mag alleen zijn eigen
 * foto's van vandaag wissen; beheer (actor-user) elke foto van de tenant.
 */
final class DeleteUnitAlbumPhotoAction
{
    public function __construct(
        private AuditRecorder $audit,
        private UnitAlbumPhotoStorage $storage,
    ) {}

    public function handle(UnitAlbumPhoto $photo, int $tenantId, ?int $actorUserId, ?Worker $worker = null): void
    {
        if ((int) $photo->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if ($worker !== null) {
            $ownToday = (int) $photo->worker_id === (int) $worker->id
                && $photo->taken_on?->toDateString() === now()->toDateString();
            if (! $ownToday) {
                throw new InvalidArgumentException('album_photo_not_own');
            }
        }

        $photoId = (int) $photo->id;
        $unitId = (int) $photo->unit_id;
        $this->storage->delete((string) $photo->path);
        $photo->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit_album.photo_deleted',
            modelType: UnitAlbumPhoto::class,
            modelId: $photoId,
            payload: [
                'unit_id' => $unitId,
                'worker_id' => $worker?->id,
            ],
        );
    }
}
