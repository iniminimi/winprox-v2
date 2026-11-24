<?php

namespace App\Actions\Time;

use App\Enums\ClockDisplayClaimStatus;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Bevestigde koppels van klokschermen aan één Clock Point, nieuwste eerst.
 * Voor de boekhouding welk serienummer wanneer actief werd (device_hint).
 */
class ListClockDisplayPairHistoryAction
{
    /**
     * @return Collection<int, ClockDisplayClaim>
     */
    public function handle(ClockPoint $clockPoint, int $tenantId, int $limit = 50): Collection
    {
        if ((int) $clockPoint->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $limit = max(1, min(100, $limit));

        return ClockDisplayClaim::query()
            ->where('clock_point_id', $clockPoint->id)
            ->where('tenant_id', $tenantId)
            ->where('status', ClockDisplayClaimStatus::Confirmed->value)
            ->whereNotNull('confirmed_at')
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'device_hint', 'confirmed_at', 'ip', 'status']);
    }
}
