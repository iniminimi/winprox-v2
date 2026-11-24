<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Enums\CameraClaimStatus;
use App\Models\CameraClaim;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class DenyCameraClaimAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(CameraClaim $claim, int $tenantId, ?int $actorUserId): void
    {
        if ((int) $claim->tenant_id !== $tenantId || $claim->status !== CameraClaimStatus::Pending) {
            throw ValidationException::withMessages([
                'claim' => [__('camera.errors.not_pending')],
            ]);
        }

        $claim->update([
            'status' => CameraClaimStatus::Denied,
            'denied_reason' => 'admin',
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'camera.claim_denied',
            modelType: CameraClaim::class,
            modelId: $claim->id,
            payload: [
                'camera_id' => $claim->camera_id,
                'reason' => 'admin',
            ],
        );
    }
}
