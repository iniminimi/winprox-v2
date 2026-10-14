<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\ResolveClockPointPortalTokenAction;
use Illuminate\Http\RedirectResponse;

/**
 * Per-token manifest-URL's (legacy): doorverwijzen naar het canonieke
 * tenant-manifest zodat alle pagina's en oude links op één app-identiteit
 * uitkomen.
 */
class ClockPointHomescreenManifestController
{
    public function __invoke(string $token, ResolveClockPointPortalTokenAction $resolveToken): RedirectResponse
    {
        $resolution = $resolveToken->handle($token);
        $clockPoint = $resolution->clockPoint;

        abort_if($clockPoint === null || ! $resolution->isUsable(), 404);

        return redirect()->route('public.time-portal.tenant.manifest', $clockPoint->tenant_id);
    }
}
