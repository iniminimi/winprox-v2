<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeExpiredTrialWarningMail;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Plant auto-purge voor verlopen trials (T+warning) en mailt alle admins.
 */
final class ScheduleExpiredTrialPurgesAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{scanned: int, scheduled: int}
     */
    public function handle(?Carbon $now = null): array
    {
        $now ??= now();
        $warningDays = max(0, (int) config('tenant_purge.expired_trial_warning_days', 7));
        $purgeDays = max($warningDays, (int) config('tenant_purge.expired_trial_purge_days', 14));
        $warningCutoff = $now->copy()->subDays($warningDays);

        $stats = ['scanned' => 0, 'scheduled' => 0];

        Tenant::query()
            ->whereNotNull('trial_ends_at')
            ->whereNull('billing_plan')
            ->where('trial_ends_at', '<=', $warningCutoff)
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Tenant $tenant) use (&$stats, $now, $purgeDays): void {
                $stats['scanned']++;

                if (! $tenant->isExpiredTrialWithoutSubscription()) {
                    return;
                }

                $hasExpiredTrialHistory = TenantPurgeRequest::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('track', TenantPurgeTrack::ExpiredTrial)
                    ->exists();

                if ($hasExpiredTrialHistory) {
                    return;
                }

                $hasOpenPurge = TenantPurgeRequest::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', [
                        TenantPurgeStatus::AwaitingEmail->value,
                        TenantPurgeStatus::Ready->value,
                        TenantPurgeStatus::Scheduled->value,
                    ])
                    ->exists();

                if ($hasOpenPurge) {
                    return;
                }

                $purgeAt = $tenant->trial_ends_at->copy()->addDays($purgeDays);
                if ($purgeAt->lte($now)) {
                    // Late catch-up: geef na waarschuwing nog één dag.
                    $purgeAt = $now->copy()->addDay();
                }

                $request = TenantPurgeRequest::query()->create([
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'track' => TenantPurgeTrack::ExpiredTrial,
                    'status' => TenantPurgeStatus::Scheduled,
                    'initiated_by_user_id' => null,
                    'scheduled_purge_at' => $purgeAt,
                ]);

                $admins = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('role', User::ROLE_ADMIN)
                    ->where('is_active', true)
                    ->where('is_superuser', false)
                    ->orderBy('id')
                    ->get();

                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(new TenantPurgeExpiredTrialWarningMail($request, $admin));
                }

                $this->audit->record(
                    userId: null,
                    tenantId: (int) $tenant->id,
                    action: 'tenant_purge.expired_trial_scheduled',
                    modelType: TenantPurgeRequest::class,
                    modelId: $request->id,
                    payload: [
                        'scheduled_purge_at' => $purgeAt->toIso8601String(),
                        'admins_mailed' => $admins->count(),
                    ],
                );

                $stats['scheduled']++;
            });

        return $stats;
    }
}
