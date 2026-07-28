<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeCompletedMail;
use App\Models\Document;
use App\Models\IssuePhoto;
use App\Models\QrLinkPhoto;
use App\Models\Tenant;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Maakt SQL-snapshot, wist media + tenant-rijen en verwijdert de tenant volledig.
 */
final class ExecuteTenantPurgeAction
{
    public function __construct(
        private CollectTenantPurgeCountsAction $collectCounts,
        private CreateTenantPurgeBackupAction $createBackup,
        private AuditRecorder $audit,
    ) {}

    public function handle(TenantPurgeRequest $request, User $actor, ?string $password = null): TenantPurgeRequest
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.not_open')],
            ]);
        }

        $tenant = $request->tenant;
        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.tenant_missing')],
            ]);
        }

        if ($request->track === TenantPurgeTrack::Trial) {
            $this->assertTrialActor($request, $actor, $password);
        } else {
            $this->assertPaidActor($request, $actor);
        }

        $adminEmails = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('is_superuser', false)
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'locale']);

        $counts = $this->collectCounts->handle($tenant);
        $counts['_executor'] = [
            'id' => $actor->id,
            'name' => $actor->name,
            'email' => $actor->email,
            'superuser' => (bool) $actor->is_superuser,
        ];

        $this->audit->record(
            userId: $actor->id,
            tenantId: (int) $tenant->id,
            action: 'tenant_purge.executing',
            modelType: TenantPurgeRequest::class,
            modelId: $request->id,
            payload: ['counts' => $counts, 'track' => $request->track->value],
        );

        $backupPath = $this->createBackup->handle($tenant, $request);
        $retentionDays = (int) config('tenant_purge.backup_retention_days', 30);
        $backupExpiresAt = now()->addDays($retentionDays);

        $this->deleteMediaFiles($tenant);
        $this->deleteSetNullTenantRows((int) $tenant->id);

        $tenantName = $tenant->name;

        // Mark complete before tenant delete: user/tenant FKs on this row are nullOnDelete.
        $request->status = TenantPurgeStatus::Completed;
        $request->executed_at = now();
        $request->executed_by_user_id = $actor->id;
        $request->backup_path = $backupPath;
        $request->backup_expires_at = $backupExpiresAt;
        $request->deleted_counts = $counts;
        $request->confirmation_token_hash = null;
        $request->save();

        DB::transaction(function () use ($tenant): void {
            $tenant->delete();
        });

        foreach ($adminEmails as $admin) {
            Mail::to($admin->email)->send(new TenantPurgeCompletedMail(
                tenantName: $tenantName,
                adminName: (string) $admin->name,
                counts: array_filter(
                    $counts,
                    fn ($key) => ! str_starts_with((string) $key, '_'),
                    ARRAY_FILTER_USE_KEY,
                ),
                backupExpiresAt: $backupExpiresAt,
                adminLocale: $admin->locale ?: 'nl',
            ));
        }

        // Strict purge: keep no tenant-specific purge history in DB.
        TenantPurgeRequest::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->delete();

        return $request;
    }

    private function assertTrialActor(TenantPurgeRequest $request, User $actor, ?string $password): void
    {
        if ($request->status !== TenantPurgeStatus::Ready) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.email_not_confirmed')],
            ]);
        }

        if ($actor->tenant_id === null
            || (int) $actor->tenant_id !== (int) $request->tenant_id
            || ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.admin_only')],
            ]);
        }

        if ($password === null || $password === '' || ! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages([
                'purge_password' => [__('subscription.purge.errors.password')],
            ]);
        }
    }

    private function assertPaidActor(TenantPurgeRequest $request, User $actor): void
    {
        if (! $actor->is_superuser) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.superuser_only')],
            ]);
        }

        if ($request->status !== TenantPurgeStatus::Scheduled) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.not_scheduled')],
            ]);
        }

        if ($request->scheduled_purge_at === null || $request->scheduled_purge_at->isFuture()) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.cooldown_active', [
                    'date' => $request->scheduled_purge_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
                ])],
            ]);
        }
    }

    private function deleteMediaFiles(Tenant $tenant): void
    {
        $disk = Storage::disk('public');

        foreach (['logo_path', 'portal_background_path'] as $field) {
            $path = $tenant->{$field} ?? null;
            if (is_string($path) && $path !== '') {
                $disk->delete($path);
            }
        }

        IssuePhoto::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->chunkById(100, function ($photos) use ($disk): void {
                foreach ($photos as $photo) {
                    if (is_string($photo->path) && $photo->path !== '') {
                        $disk->delete($photo->path);
                    }
                }
            });

            Document::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->chunkById(100, function ($docs) use ($disk): void {
                foreach ($docs as $doc) {
                    $path = $doc->file_path ?? null;
                    if (is_string($path) && $path !== '') {
                        $disk->delete($path);
                    }
                }
            });

        if (class_exists(QrLinkPhoto::class)) {
            QrLinkPhoto::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->chunkById(100, function ($photos) use ($disk): void {
                    foreach ($photos as $photo) {
                        if (is_string($photo->path) && $photo->path !== '') {
                            $disk->delete($photo->path);
                        }
                    }
                });
        }
    }

    private function deleteSetNullTenantRows(int $tenantId): void
    {
        // These tables use tenant_id -> nullOnDelete; strict purge requires hard delete.
        DB::table('audit_logs')->where('tenant_id', $tenantId)->delete();
        DB::table('contact_messages')->where('tenant_id', $tenantId)->delete();
    }
}
