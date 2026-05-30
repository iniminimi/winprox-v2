<?php

namespace App\Http\Controllers\Locations;

use App\Models\Unit;
use App\Support\Qr\TeamQrCode;

final class UnitQrController
{
    public function __invoke(Unit $unit): \Illuminate\Contracts\View\View
    {
        abort_unless(
            auth()->user()->is_superuser || (int) auth()->user()->tenant_id === (int) $unit->tenant_id,
            403,
        );

        $url = route('public.unit-portal', $unit->qr_token);

        return view('locations.unit-qr', [
            'unit' => $unit,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
        ]);
    }
}
