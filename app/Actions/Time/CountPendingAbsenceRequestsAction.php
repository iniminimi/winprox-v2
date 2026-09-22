<?php

namespace App\Actions\Time;

use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Models\AbsenceRequest;
use App\Support\Time\TimeModuleAccess;

class CountPendingAbsenceRequestsAction
{
    public function handle(int $tenantId): int
    {
        return array_sum($this->byKind($tenantId));
    }

    /**
     * @return array{leave: int, recup: int}
     */
    public function byKind(int $tenantId): array
    {
        TimeModuleAccess::assertEnabledForTenantId($tenantId);

        $counts = AbsenceRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('status', AbsenceRequestStatus::Pending)
            ->toBase()
            ->selectRaw('kind, COUNT(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind');

        return [
            'leave' => (int) ($counts[ShiftTypeKind::Leave->value] ?? 0),
            'recup' => (int) ($counts[ShiftTypeKind::Recup->value] ?? 0),
        ];
    }
}
