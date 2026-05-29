<?php

namespace App\Http\Controllers\Team;

use App\Models\InternalTeam;
use App\Support\Qr\TeamQrCode;
use Illuminate\Contracts\View\View;

/**
 * Printbare team-QR. De QR linkt naar het publieke team-veldportaal
 * (route public.team-portal). Route-model-binding op InternalTeam respecteert
 * de tenant-scope, dus teams van een andere tenant geven 404.
 */
class TeamQrController
{
    public function __invoke(InternalTeam $team): View
    {
        $url = route('public.team-portal', $team->field_qr_token);

        return view('team.qr', [
            'team' => $team,
            'url' => $url,
            'qrSvg' => TeamQrCode::svg($url),
        ]);
    }
}
