<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Mail\TenantPurgeReminderMail;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Stuurt T−N reminder naar alle admins voor geplande purges (paid + expired_trial).
 */
final class SendTenantPurgeRemindersAction
{
    /**
     * @return array{scanned: int, sent: int}
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $daysBefore = (int) config('tenant_purge.reminder_days_before', 2);
        $windowStart = $now->copy()->addDays($daysBefore)->startOfDay();
        $windowEnd = $now->copy()->addDays($daysBefore)->endOfDay();

        $stats = ['scanned' => 0, 'sent' => 0];

        TenantPurgeRequest::query()
            ->where('status', TenantPurgeStatus::Scheduled)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('scheduled_purge_at')
            ->whereNotNull('tenant_id')
            ->whereBetween('scheduled_purge_at', [$windowStart, $windowEnd])
            ->orderBy('id')
            ->each(function (TenantPurgeRequest $request) use (&$stats): void {
                $stats['scanned']++;

                $admins = User::query()
                    ->where('tenant_id', $request->tenant_id)
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->where('is_superuser', false)
                    ->orderBy('id')
                    ->get();

                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(new TenantPurgeReminderMail($request, $admin));
                }

                $request->reminder_sent_at = now();
                $request->save();
                $stats['sent']++;
            });

        return $stats;
    }
}
