<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/** Zet het fotoalbum aan of uit voor de hele organisatie. */
final class UpdateTenantPhotoAlbumAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, bool $enabled, int $actorUserId): Tenant
    {
        if ($tenant->checkmateMode()) {
            throw new InvalidArgumentException('checkmate');
        }

        $tenant->update(['photo_album_enabled' => $enabled]);
        $fresh = $tenant->refresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.photo_album_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: ['photo_album_enabled' => $enabled],
        );

        return $fresh;
    }
}
