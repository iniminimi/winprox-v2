<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeConfirmMail;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Start een tenant-purge-aanvraag (admin + wachtwoord + export-bevestiging).
 */
final class StartTenantPurgeRequestAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Tenant $tenant,
        User $actor,
        string $password,
        bool $exportAcknowledged,
    ): TenantPurgeRequest {
        if ($actor->tenant_id === null || (int) $actor->tenant_id !== (int) $tenant->id || ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.admin_only')],
            ]);
        }

        if (! $exportAcknowledged) {
            throw ValidationException::withMessages([
                'purge_export' => [__('subscription.purge.errors.export_required')],
            ]);
        }

        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages([
                'purge_password' => [__('subscription.purge.errors.password')],
            ]);
        }

        $open = TenantPurgeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', [
                TenantPurgeStatus::AwaitingEmail->value,
                TenantPurgeStatus::Ready->value,
                TenantPurgeStatus::Scheduled->value,
            ])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.already_open')],
            ]);
        }

        $plainToken = Str::random(64);
        $track = $tenant->purgeTrack();

        $request = TenantPurgeRequest::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'track' => $track,
            'status' => TenantPurgeStatus::AwaitingEmail,
            'initiated_by_user_id' => $actor->id,
            'export_acknowledged_at' => now(),
            'password_verified_at' => now(),
            'confirmation_token_hash' => hash('sha256', $plainToken),
        ]);

        $admins = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('is_superuser', false)
            ->orderBy('id')
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new TenantPurgeConfirmMail($request, $admin, $plainToken, $track));
        }

        $this->audit->record(
            userId: $actor->id,
            tenantId: (int) $tenant->id,
            action: 'tenant_purge.started',
            modelType: TenantPurgeRequest::class,
            modelId: $request->id,
            payload: ['track' => $track->value],
        );

        return $request;
    }
}
