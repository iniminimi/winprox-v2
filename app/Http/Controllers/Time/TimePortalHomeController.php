<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\ResolveTenantHomescreenPointAction;
use Illuminate\Http\RedirectResponse;

/**
 * Stabiele startpagina van de geïnstalleerde PWA. Verandert nooit van URL:
 * verwijst door naar een live Clock Point van de tenant.
 */
class TimePortalHomeController
{
    public function __invoke(int $tenant, ResolveTenantHomescreenPointAction $resolve): RedirectResponse
    {
        $clockPoint = $resolve->handle($tenant);

        abort_if($clockPoint === null, 404);

        return redirect()->route('public.time-portal.cp', $clockPoint->qr_token);
    }
}
