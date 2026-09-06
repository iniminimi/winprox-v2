<?php

namespace App\Actions\Time;

use App\Models\Tenant;
use App\Support\Portal\PortalAccess;
use App\Support\Time\TimeModuleAccess;

class ResolveTimeRosterQrTokenAction
{
    /**
     * @return array{tenant: Tenant, inactiveReasonKey: ?string}|null
     */
    public function handle(string $token): ?array
    {
        $token = strtolower(trim($token));
        if ($token === '' || strlen($token) < 20) {
            return null;
        }

        $tenant = Tenant::query()->where('time_roster_qr_token', $token)->first();
        if ($tenant === null) {
            return null;
        }

        if (! TimeModuleAccess::tenantHasModule($tenant)) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'inactiveReasonKey' => PortalAccess::tenantPortalInactiveReasonKey($tenant),
        ];
    }
}
