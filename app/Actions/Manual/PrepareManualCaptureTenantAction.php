<?php

declare(strict_types=1);

namespace App\Actions\Manual;

use App\Models\Tenant;
use App\Models\User;
use InvalidArgumentException;

final class PrepareManualCaptureTenantAction
{
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

        if (! $tenant->hasEsgModule()) {
            $tenant->update(['has_esg_module' => true]);
            $tenant->refresh();
        }

        return $tenant;
    }
}
