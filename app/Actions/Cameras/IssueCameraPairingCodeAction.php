<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Enums\CameraClaimStatus;
use App\Models\Camera;
use App\Models\CameraClaim;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueCameraPairingCodeAction
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, int $tenantId, ?int $actorUserId): string
    {
        if ((int) $unit->tenant_id !== $tenantId || ! $unit->is_active) {
            throw ValidationException::withMessages([
                'unit' => [__('camera.errors.unit_invalid')],
            ]);
        }

        return DB::transaction(function () use ($unit, $tenantId, $actorUserId) {
            $camera = Camera::query()
                ->where('unit_id', $unit->id)
                ->lockForUpdate()
                ->first();

            if ($camera === null) {
                $camera = Camera::query()->create([
                    'tenant_id' => $tenantId,
                    'unit_id' => $unit->id,
                    'forwarding_enabled' => false,
                ]);
            }

            CameraClaim::query()
                ->where('camera_id', $camera->id)
                ->activePending()
                ->lockForUpdate()
                ->get()
                ->each(function (CameraClaim $claim) use ($tenantId, $actorUserId): void {
                    $claim->update([
                        'status' => CameraClaimStatus::Denied,
                        'denied_reason' => 'code_reissued',
                    ]);

                    $this->audit->record(
                        userId: $actorUserId,
                        tenantId: $tenantId,
                        action: 'camera.claim_denied',
                        modelType: CameraClaim::class,
                        modelId: $claim->id,
                        payload: [
                            'camera_id' => $claim->camera_id,
                            'reason' => 'code_reissued',
                        ],
                    );
                });

            $ttlMinutes = max(1, (int) config('time.display_pairing_ttl_minutes', 10));
            $code = '';
            $max = strlen(self::ALPHABET) - 1;
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, $max)];
            }

            $camera->update([
                'pairing_code' => $code,
                'pairing_expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'camera.pairing_issued',
                modelType: Camera::class,
                modelId: $camera->id,
                payload: ['unit_id' => $unit->id],
            );

            return $code;
        });
    }
}
