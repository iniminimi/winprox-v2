<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Actions\Sense\CreateSensePointAction;
use App\Enums\ScreenClaimStatus;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfirmScreenClaimAction
{
    public function __construct(
        private AuditRecorder $audit,
        private CreateSensePointAction $createPoint,
    ) {}

    public function handle(ScreenClaim $claim, int $tenantId, ?int $actorUserId): ScreenClaim
    {
        if ((int) $claim->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'claim' => [__('screen.errors.not_pending')],
            ]);
        }

        return DB::transaction(function () use ($claim, $tenantId, $actorUserId) {
            $locked = ScreenClaim::query()->whereKey($claim->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== ScreenClaimStatus::Pending || $locked->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'claim' => [__('screen.errors.not_pending')],
                ]);
            }

            $screen = Screen::query()->whereKey($locked->screen_id)->lockForUpdate()->first();

            if ($screen === null || $screen->unit_id === null) {
                throw ValidationException::withMessages([
                    'claim' => [__('screen.errors.not_pending')],
                ]);
            }

            $plain = $screen->has_sense
                ? $this->senseKey($screen, $tenantId, $actorUserId)
                : null;
            $displayId = $screen->display_id ?? $this->displayId();

            $screen->update([
                'display_id' => $displayId,
                'display_secret' => $screen->display_secret ?? bin2hex(random_bytes(32)),
                'qr_window_seconds' => 3600,
                'device_hint' => $locked->device_hint,
                'paired_at' => now(),
                'last_seen_at' => now(),
                'pairing_code' => null,
                'pairing_expires_at' => null,
            ]);

            $locked->update([
                'status' => ScreenClaimStatus::Confirmed,
                'issued_token' => $plain,
                'confirmed_at' => now(),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.claim_confirmed',
                modelType: ScreenClaim::class,
                modelId: $locked->id,
                payload: [
                    'screen_id' => $screen->id,
                    'unit_id' => $screen->unit_id,
                    'device_hint' => $locked->device_hint,
                ],
            );

            return $locked;
        });
    }

    private function senseKey(Screen $screen, int $tenantId, ?int $actorUserId): string
    {
        $point = SensePoint::query()->where('screen_id', $screen->id)->first();

        if ($point === null) {
            return $this->createPoint->handle((int) $screen->unit_id, $tenantId, $actorUserId)['plain_token'];
        }

        $token = SensePoint::issueToken();
        $point->update([
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
        ]);

        return $token['plain'];
    }

    private function displayId(): string
    {
        do {
            $displayId = Str::lower(Str::random(24));
        } while (Screen::query()->withoutGlobalScopes()->where('display_id', $displayId)->exists());

        return $displayId;
    }
}
