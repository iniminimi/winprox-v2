<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\Camera;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class UnlinkCameraAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Camera $camera, int $tenantId, ?int $actorUserId): void
    {
        if ((int) $camera->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'camera' => [__('camera.errors.unit_invalid')],
            ]);
        }

        $camera->update([
            'forwarding_enabled' => false,
            'pairing_code' => null,
            'pairing_expires_at' => null,
            'device_hint' => null,
            'paired_at' => null,
            'token_hash' => null,
            'token_prefix' => null,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'camera.unlinked',
            modelType: Camera::class,
            modelId: $camera->id,
            payload: ['unit_id' => $camera->unit_id],
        );
    }
}
