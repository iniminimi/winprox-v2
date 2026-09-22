<?php

namespace App\Actions\Time;

use App\Data\Time\RequestAbsenceData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\ShiftTypeKind;
use App\Events\Time\AbsenceRequested;
use App\Models\AbsenceRequest;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use App\Support\Validation\TextDescriptionLimits;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RequestAbsenceAction
{
    public const MAX_DAYS = 31;

    public function handle(Tenant $tenant, Worker $worker, RequestAbsenceData $data): AbsenceRequest
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        if (! $data->kind->isRequestableAbsence()) {
            throw new InvalidArgumentException('invalid_kind');
        }

        $from = $this->parseDate($data->dateFrom);
        $to = $this->parseDate($data->dateTo);
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('range_inverted');
        }

        if ($to->lt($from)) {
            throw new InvalidArgumentException('range_inverted');
        }

        $today = CarbonImmutable::now()->startOfDay();
        if ($from->lt($today)) {
            throw new InvalidArgumentException('date_in_past');
        }

        $days = $from->diffInDays($to) + 1;
        if ($days > self::MAX_DAYS) {
            throw new InvalidArgumentException('range_too_long');
        }

        $shiftType = $this->firstActiveType((int) $tenant->id, $data->kind);
        if ($shiftType === null) {
            throw new InvalidArgumentException('no_shift_type');
        }

        if ($this->hasOverlappingPending((int) $tenant->id, (int) $worker->id, $from, $to)) {
            throw new InvalidArgumentException('overlapping_pending');
        }

        $description = $this->normalizeDescription($data->description);

        $request = AbsenceRequest::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
            'kind' => $data->kind,
            'shift_type_id' => $shiftType->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'description' => $description,
            'status' => AbsenceRequestStatus::Pending,
        ]);

        AbsenceRequested::dispatch(
            (int) $tenant->id,
            (int) $request->id,
            (int) $worker->id,
            $data->kind->value,
            $from->toDateString(),
            $to->toDateString(),
        );

        return $request->fresh(['worker', 'shiftType']) ?? $request;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstActiveType(int $tenantId, ShiftTypeKind $kind): ?ShiftType
    {
        return ShiftType::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', $kind)
            ->where('is_active', true)
            ->orderBy('code')
            ->first();
    }

    private function hasOverlappingPending(
        int $tenantId,
        int $workerId,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): bool {
        return AbsenceRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('worker_id', $workerId)
            ->where('status', AbsenceRequestStatus::Pending)
            ->whereDate('date_from', '<=', $to->toDateString())
            ->whereDate('date_to', '>=', $from->toDateString())
            ->exists();
    }

    private function normalizeDescription(?string $description): ?string
    {
        $trimmed = trim((string) $description);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, TextDescriptionLimits::MAX);
    }
}
