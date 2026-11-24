<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Enums\CameraClaimStatus;
use App\Models\Camera;
use App\Models\CameraClaim;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitCameraClaimAction
{
    public function handle(string $code, string $deviceHint, ?string $ip): CameraClaim
    {
        $normalized = strtoupper(str_replace('-', '', trim($code)));

        $camera = Camera::query()
            ->withoutGlobalScopes()
            ->where('pairing_code', $normalized)
            ->where('pairing_expires_at', '>', now())
            ->first();

        if ($camera === null) {
            throw ValidationException::withMessages([
                'code' => [__('camera.errors.code_invalid')],
            ]);
        }

        CameraClaim::query()
            ->withoutGlobalScopes()
            ->where('camera_id', $camera->id)
            ->where('status', CameraClaimStatus::Pending->value)
            ->get()
            ->each(function (CameraClaim $claim): void {
                $claim->update([
                    'status' => CameraClaimStatus::Denied,
                    'denied_reason' => 'replaced',
                ]);
            });

        $ttlMinutes = max(1, (int) config('time.display_pairing_ttl_minutes', 10));

        return CameraClaim::query()->create([
            'tenant_id' => $camera->tenant_id,
            'camera_id' => $camera->id,
            'claim_token' => Str::lower(Str::random(40)),
            'pairing_code' => $normalized,
            'device_hint' => $deviceHint,
            'ip' => $ip,
            'status' => CameraClaimStatus::Pending,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
