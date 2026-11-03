<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerLeaveEntitlement;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SetWorkerLeaveEntitlementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Tenant $tenant,
        Worker $worker,
        int $year,
        ?string $days,
        int $nextYear,
        ?string $nextDays,
        ?int $actorUserId,
    ): void {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        if ((int) $worker->tenant_id !== (int) $tenant->id) {
            throw new InvalidArgumentException('worker_tenant_mismatch');
        }

        $thisYear = $this->halves($days, 'workerLeaveDaysThisYear');
        $following = $this->halves($nextDays, 'workerLeaveDaysNextYear');

        DB::transaction(function () use ($tenant, $worker, $year, $thisYear, $nextYear, $following): void {
            $this->write($tenant, $worker, $year, $thisYear);
            $this->write($tenant, $worker, $nextYear, $following);
        });

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'worker.leave_entitlement_set',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [
                $year => $thisYear === null ? null : $thisYear / 2,
                $nextYear => $following === null ? null : $following / 2,
            ],
        );
    }

    private function write(Tenant $tenant, Worker $worker, int $year, ?int $halves): void
    {
        $existing = WorkerLeaveEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->where('worker_id', $worker->id)
            ->where('year', $year)
            ->first();

        if ($halves === null) {
            $existing?->delete();

            return;
        }

        $days = number_format($halves / 2, 1, '.', '');
        if ($existing instanceof WorkerLeaveEntitlement) {
            $existing->update(['days' => $days]);

            return;
        }

        WorkerLeaveEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
            'year' => $year,
            'days' => $days,
        ]);
    }

    private function halves(?string $raw, string $field): ?int
    {
        $raw = trim(str_replace(',', '.', (string) $raw));
        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw ValidationException::withMessages([
                $field => __('team.errors.leave_days_invalid'),
            ]);
        }

        $value = (float) $raw;
        $halves = (int) round($value * 2);
        if ($value < 0 || $value > 366 || abs(($value * 2) - $halves) > 0.001) {
            throw ValidationException::withMessages([
                $field => __('team.errors.leave_days_invalid'),
            ]);
        }

        return $halves;
    }
}
