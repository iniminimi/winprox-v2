<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\ClockPointQrToken;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RenewClockPointQrAction
{
    public function __construct(
        private ScheduleClockPointQrRecommendationAction $scheduleRecommendation,
        private AuditRecorder $audit,
    ) {}

    public function handle(ClockPoint $clockPoint, int $tenantId, ?int $actorUserId): ClockPoint
    {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($clockPoint, $tenantId, $actorUserId) {
            $locked = ClockPoint::query()
                ->whereKey($clockPoint->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('clock_point_not_found');
            }

            $oldToken = (string) $locked->qr_token;
            $graceDays = max(1, (int) config('time.qr_grace_days', 7));

            ClockPointQrToken::query()->create([
                'tenant_id' => $locked->tenant_id,
                'clock_point_id' => $locked->id,
                'qr_token' => $oldToken,
                'grace_ends_at' => now()->addDays($graceDays),
            ]);

            do {
                $newToken = Str::lower(Str::random(40));
            } while (
                ClockPoint::withoutGlobalScope('tenant')->where('qr_token', $newToken)->exists()
                || ClockPointQrToken::withoutGlobalScope('tenant')->where('qr_token', $newToken)->exists()
            );

            $renewedAt = now();
            $locked->update([
                'qr_token' => $newToken,
                'qr_renewed_at' => $renewedAt,
            ]);

            $tenant = Tenant::query()->findOrFail($tenantId);
            $locked = $this->scheduleRecommendation->handle($locked->fresh(), $tenant, $renewedAt);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_point.qr_renewed',
                modelType: ClockPoint::class,
                modelId: $locked->id,
                payload: [
                    'clock_point_id' => $locked->id,
                    'previous_token' => $oldToken,
                    'grace_days' => $graceDays,
                ],
            );

            return $locked;
        });
    }
}
