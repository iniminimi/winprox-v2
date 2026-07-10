<?php

namespace App\Http\Controllers\Time;

use App\Models\ClockPoint;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\TeamQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ClockPointQrController
{
    public function __invoke(ClockPoint $clockPoint): View
    {
        Gate::authorize('view', $clockPoint);

        $clockPoint->load('tenant', 'location');
        $url = route('public.time-portal', $clockPoint->qr_token);

        return view('time.qr', [
            'clockPoint' => $clockPoint,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
            'centerLogoUrl' => QrCenterLogo::publicUrl($clockPoint->tenant),
        ]);
    }
}
