<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\Camera;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class SetCameraForwardingAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Camera $camera, bool $enabled, int $tenantId, ?int $actorUserId): void
    {
        if ((int) $camera->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'camera' => [__('camera.errors.unit_invalid')],
            ]);
        }

        $camera->update(['forwarding_enabled' => $enabled]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'camera.forwarding_updated',
            modelType: Camera::class,
            modelId: $camera->id,
            payload: [
                'unit_id' => $camera->unit_id,
                'forwarding_enabled' => $enabled,
            ],
        );
    }
}
