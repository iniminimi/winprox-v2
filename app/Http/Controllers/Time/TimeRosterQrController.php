<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\EnsureTimeRosterQrTokenAction;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrSvg;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TimeRosterQrController
{
    public function __invoke(EnsureTimeRosterQrTokenAction $ensureToken): View
    {
        Gate::authorize('viewAny', WorkShift::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $tenant = $ensureToken->handle($tenant, auth()->id());
        $url = $tenant->timeRosterPortalUrl();
        if ($url === null) {
            abort(404);
        }

        return view('time.roster-qr', [
            'tenant' => $tenant,
            'url' => $url,
            'qrSvg' => QrSvg::svg($url),
            'centerLogoUrl' => QrCenterLogo::publicUrl($tenant),
        ]);
    }
}
