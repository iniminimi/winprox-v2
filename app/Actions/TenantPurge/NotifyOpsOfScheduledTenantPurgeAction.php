<?php

namespace App\Actions\TenantPurge;

use App\Mail\TenantPurgeScheduledToOpsMail;
use App\Models\TenantPurgeRequest;
use Illuminate\Support\Facades\Mail;

/**
 * Stuurt een interne melding naar WinProx (info@) wanneer een purge gepland is.
 */
final class NotifyOpsOfScheduledTenantPurgeAction
{
    public function handle(TenantPurgeRequest $request): bool
    {
        $to = trim((string) config(
            'tenant_purge.ops_notification_email',
            config('winprox.new_tenant_notification_email', 'info@winprox.app'),
        ));

        if ($to === '') {
            return false;
        }

        Mail::to($to)->send(new TenantPurgeScheduledToOpsMail($request));

        return true;
    }
}
