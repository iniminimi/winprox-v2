<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EnsureTimeRosterQrTokenAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): Tenant
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        $existing = trim((string) $tenant->time_roster_qr_token);
        if ($existing !== '') {
            return $tenant;
        }

        $token = $this->uniqueToken();
        $tenant->forceFill(['time_roster_qr_token' => $token])->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'time.roster_qr.created',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['token_suffix' => substr($token, -6)],
        );

        return $tenant->refresh();
    }

    private function uniqueToken(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $token = Str::lower(Str::random(40));
            if (! Tenant::query()->where('time_roster_qr_token', $token)->exists()) {
                return $token;
            }
        }

        throw new InvalidArgumentException('time_roster_qr_token_collision');
    }
}
