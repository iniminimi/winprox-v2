<?php

declare(strict_types=1);

namespace App\Actions\Manual;

use App\Actions\Time\EnsureDefaultClockPointAction;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use InvalidArgumentException;

final class PrepareManualCaptureTenantAction
{
    public function __construct(
        private EnsureDefaultClockPointAction $ensureDefaultClockPoint,
    ) {}

    public function handle(?string $email = null): Tenant
    {
        $email = trim((string) ($email ?? config('manual_capture.email')));

        if ($email === '') {
            throw new InvalidArgumentException('manual_capture_not_configured');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new InvalidArgumentException('manual_capture_user_not_found');
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            throw new InvalidArgumentException('manual_capture_user_has_no_tenant');
        }

        $updates = [];

        if (! $tenant->hasEsgModule()) {
            $updates['has_esg_module'] = true;
        }

        if (! $tenant->hasTimeModule()) {
            $updates['has_time_module'] = true;
        }

        if (! $tenant->hasIotModule()) {
            $updates['has_iot_module'] = true;
        }

        if ($updates !== []) {
            $tenant->update($updates);
            $tenant->refresh();
        }

        $this->ensureDefaultClockPoint->handle(
            $tenant,
            __('team.clock_point_qr.default_name'),
            $user->id,
        );

        return $tenant->refresh();
    }

    public function clockPointQrToken(Tenant $tenant): ?string
    {
        return ClockPoint::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->value('qr_token');
    }
}
