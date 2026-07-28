<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeScheduledMail;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Bevestigt de purge via e-mailtoken (admin met toegang tot mailbox).
 */
final class ConfirmTenantPurgeEmailAction
{
    public function __construct(
        private AuditRecorder $audit,
        private NotifyOpsOfScheduledTenantPurgeAction $notifyOps,
    ) {}

    public function handle(TenantPurgeRequest $request, User $actor, string $plainToken): TenantPurgeRequest
    {
        if (! $request->isOpen() || $request->status !== TenantPurgeStatus::AwaitingEmail) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.not_awaiting_email')],
            ]);
        }

        if ($request->tenant_id === null
            || $actor->tenant_id === null
            || (int) $actor->tenant_id !== (int) $request->tenant_id
            || ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.admin_only')],
            ]);
        }

        $hours = (int) config('tenant_purge.confirm_token_hours', 48);
        if ($request->created_at !== null && $request->created_at->lt(now()->subHours($hours))) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.token_expired')],
            ]);
        }

        $hash = hash('sha256', $plainToken);
        if (! hash_equals((string) $request->confirmation_token_hash, $hash)) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.token_invalid')],
            ]);
        }

        $request->email_confirmed_at = now();
        $request->email_confirmed_by_user_id = $actor->id;
        $request->confirmation_token_hash = null;

        if ($request->track === TenantPurgeTrack::Trial) {
            $request->status = TenantPurgeStatus::Ready;
        } else {
            $cooldownDays = (int) config('tenant_purge.paid_cooldown_days', 7);
            $request->status = TenantPurgeStatus::Scheduled;
            $request->scheduled_purge_at = now()->addDays($cooldownDays);
        }

        $request->save();

        if ($request->track === TenantPurgeTrack::Paid && $request->tenant_id !== null) {
            $fresh = $request->fresh();

            $admins = User::query()
                ->where('tenant_id', $request->tenant_id)
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->where('is_superuser', false)
                ->orderBy('id')
                ->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new TenantPurgeScheduledMail($fresh, $admin));
            }

            $this->notifyOps->handle($fresh);
        }

        $this->audit->record(
            userId: $actor->id,
            tenantId: (int) $request->tenant_id,
            action: 'tenant_purge.email_confirmed',
            modelType: TenantPurgeRequest::class,
            modelId: $request->id,
            payload: [
                'track' => $request->track->value,
                'status' => $request->status->value,
                'scheduled_purge_at' => $request->scheduled_purge_at?->toIso8601String(),
            ],
        );

        return $request->fresh();
    }
}
