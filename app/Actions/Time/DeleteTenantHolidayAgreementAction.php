<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Support\Audit\AuditRecorder;
use Carbon\Carbon;

class DeleteTenantHolidayAgreementAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, string $holidayDate, ?int $actorUserId = null): void
    {
        $holidayDate = Carbon::parse($holidayDate)->toDateString();
        TenantHolidayAgreement::query()
            ->where('tenant_id', $tenant->id)
            ->whereDate('holiday_date', $holidayDate)
            ->delete();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.holiday_agreement_deleted',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['holiday_date' => $holidayDate],
        );
    }
}
