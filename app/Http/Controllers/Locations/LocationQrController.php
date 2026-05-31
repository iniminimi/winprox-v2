<?php

namespace App\Http\Controllers\Locations;

use App\Models\Location;
use App\Support\Platform\SuperuserTenantAccess;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\TeamQrCode;

final class LocationQrController
{
    public function __invoke(Location $location): \Illuminate\Contracts\View\View
    {
        abort_unless(
            SuperuserTenantAccess::canAccessTenant(auth()->user(), (int) $location->tenant_id),
            403,
        );

        $location->load('tenant');
        $url = route('public.location-portal', $location->location_qr_token);

        return view('locations.qr', [
            'location' => $location,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
            'centerLogoUrl' => QrCenterLogo::publicUrl($location->tenant),
        ]);
    }
}
