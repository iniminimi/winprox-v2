<?php

namespace App\Actions\Public;

use App\Models\QrReportEmailHold;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert onbevestigde, verlopen holds (inclusief foto's) en ruimt oude
 * bevestigde rijen op. Issue-foto's blijven staan.
 */
class ExpireQrReportEmailHoldsAction
{
    public function handle(): int
    {
        $expired = QrReportEmailHold::withoutGlobalScopes()
            ->whereNull('confirmed_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $hold) {
            foreach ($hold->storedPhotoPaths() as $path) {
                Storage::disk('public')->delete($path);
            }
            $hold->delete();
            $count++;
        }

        $retainDays = max(1, (int) config('portal.qr_report_email_verification.confirmed_retain_days', 7));
        $pruned = QrReportEmailHold::withoutGlobalScopes()
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '<=', now()->subDays($retainDays))
            ->delete();

        return $count + (int) $pruned;
    }
}
