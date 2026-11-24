<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenClaimStatus;
use App\Enums\ScreenRole;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueScreenPairingCodeAction
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, int $tenantId, ?int $actorUserId): string
    {
        if ((int) $unit->tenant_id !== $tenantId || ! $unit->is_active) {
            throw ValidationException::withMessages([
                'unit' => [__('screen.errors.unit_invalid')],
            ]);
        }

        return DB::transaction(function () use ($unit, $tenantId, $actorUserId) {
            $screen = Screen::query()
                ->where('unit_id', $unit->id)
                ->lockForUpdate()
                ->first();

            if ($screen === null) {
                $screen = Screen::query()->create([
                    'tenant_id' => $tenantId,
                    'unit_id' => $unit->id,
                    'role' => ScreenRole::Sense,
                    'qr_window_seconds' => 3600,
                ]);
            }

            ScreenClaim::query()
                ->where('screen_id', $screen->id)
                ->activePending()
                ->lockForUpdate()
                ->get()
                ->each(function (ScreenClaim $claim) use ($tenantId, $actorUserId): void {
                    $claim->update([
                        'status' => ScreenClaimStatus::Denied,
                        'denied_reason' => 'code_reissued',
                    ]);

                    $this->audit->record(
                        userId: $actorUserId,
                        tenantId: $tenantId,
                        action: 'screen.claim_denied',
                        modelType: ScreenClaim::class,
                        modelId: $claim->id,
                        payload: [
                            'screen_id' => $claim->screen_id,
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

            $screen->update([
                'pairing_code' => $code,
                'pairing_expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.pairing_issued',
                modelType: Screen::class,
                modelId: $screen->id,
                payload: [
                    'unit_id' => $unit->id,
                    'role' => ScreenRole::Sense->value,
                ],
            );

            return $code;
        });
    }
}
