<?php

namespace App\Http\Controllers\Locations;

use App\Models\Unit;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\TeamQrCode;
use App\Support\Qr\UnitPortalUrl;

final class UnitQrController
{
    public function __invoke(Unit $unit): \Illuminate\Contracts\View\View
    {
        abort_unless(
            SuperuserTenantAccess::canAccessTenant(auth()->user(), (int) $unit->tenant_id),
            403,
        );

        $unit->load('tenant');
        $url = UnitPortalUrl::forUnit($unit);

        return view('locations.unit-qr', [
            'unit' => $unit,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
            'centerLogoUrl' => QrCenterLogo::publicUrl($unit->tenant),
        ]);
    }
}
