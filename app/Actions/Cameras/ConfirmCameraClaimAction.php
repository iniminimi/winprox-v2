<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Enums\CameraClaimStatus;
use App\Models\Camera;
use App\Models\CameraClaim;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmCameraClaimAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(CameraClaim $claim, int $tenantId, ?int $actorUserId): CameraClaim
    {
        if ((int) $claim->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'claim' => [__('camera.errors.not_pending')],
            ]);
        }

        return DB::transaction(function () use ($claim, $tenantId, $actorUserId) {
            $locked = CameraClaim::query()->whereKey($claim->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== CameraClaimStatus::Pending || $locked->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'claim' => [__('camera.errors.not_pending')],
                ]);
            }

            $camera = Camera::query()->whereKey($locked->camera_id)->lockForUpdate()->first();
            if ($camera === null) {
                throw ValidationException::withMessages([
                    'claim' => [__('camera.errors.not_pending')],
                ]);
            }

            $token = Camera::issueToken();

            $camera->update([
                'token_hash' => $token['hash'],
                'token_prefix' => $token['prefix'],
                'device_hint' => $locked->device_hint,
                'paired_at' => now(),
                'last_seen_at' => now(),
                'pairing_code' => null,
                'pairing_expires_at' => null,
            ]);

            $locked->update([
                'status' => CameraClaimStatus::Confirmed,
                'issued_token' => $token['plain'],
                'confirmed_at' => now(),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'camera.claim_confirmed',
                modelType: CameraClaim::class,
                modelId: $locked->id,
                payload: [
                    'camera_id' => $camera->id,
                    'unit_id' => $camera->unit_id,
                    'device_hint' => $locked->device_hint,
                ],
            );

            return $locked;
        });
    }
}
