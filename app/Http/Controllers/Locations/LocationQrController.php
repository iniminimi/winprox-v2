<?php

namespace App\Http\Controllers\Locations;

use App\Models\Location;
use App\Support\Qr\TeamQrCode;

final class LocationQrController
{
    public function __invoke(Location $location): \Illuminate\Contracts\View\View
    {
        abort_unless(
            auth()->user()->is_superuser || (int) auth()->user()->tenant_id === (int) $location->tenant_id,
            403,
        );

        $url = route('public.location-portal', $location->location_qr_token);

        return view('locations.qr', [
            'location' => $location,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
        ]);
    }
}
