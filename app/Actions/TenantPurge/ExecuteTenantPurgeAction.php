<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Enums\TenantPurgeTrack;
use App\Mail\TenantPurgeCompletedMail;
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
    /**
     * Kolommen met paden op de publieke disk. `tenants` matcht op id, de rest op tenant_id.
     * Nieuwe upload-kolom? Voeg hem hier toe; TenantPurgeTest faalt anders.
     */
    public const MEDIA_PATH_COLUMNS = [
        'tenants' => ['logo_path', 'portal_background_path', 'shell_background_path'],
        'clock_display_images' => ['path'],
        'documents' => ['file_path'],
        'issue_photos' => ['path'],
        'qr_link_photos' => ['path'],
        'qr_report_email_holds' => ['photo_paths'],
        'tenant_qr_sticker_sheet_settings' => ['background_path'],
        'unit_check_photos' => ['path'],
        'units' => ['background_photo_path'],
        'workers' => ['photo_path'],
    ];

    /** Mappen op de publieke disk met de tenant-id in het pad (vangt ook losse bestanden op). */
    public const MEDIA_TENANT_DIRECTORIES = [
        'tenant-logos',
        'tenant-portal-backgrounds',
        'tenant-qr-sticker-backgrounds',
        'location-documents',
    ];

    public function __construct(
        private CollectTenantPurgeCountsAction $collectCounts,
        private CreateTenantPurgeBackupAction $createBackup,
        private AuditRecorder $audit,
    ) {}

    public function handle(TenantPurgeRequest $request, ?User $actor = null, ?string $password = null): TenantPurgeRequest
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

        match ($request->track) {
            TenantPurgeTrack::Trial => $this->assertTrialActor($request, $actor, $password),
            TenantPurgeTrack::Paid => $this->assertPaidActor($request, $actor),
            TenantPurgeTrack::ExpiredTrial => $this->assertExpiredTrialReady($request),
            TenantPurgeTrack::Unused => $this->assertUnusedActor($request, $actor),
        };

        // Nooit-gebruikte accounts: geen afsluitmail naar een (mogelijk valse) inbox.
        $adminEmails = $request->track === TenantPurgeTrack::Unused
            ? collect()
            : User::query()
                ->where('tenant_id', $tenant->id)
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->where('is_superuser', false)
                ->orderBy('id')
                ->get(['id', 'name', 'email', 'locale']);

        $counts = $this->collectCounts->handle($tenant);
        $counts['_executor'] = [
            'id' => $actor?->id,
            'name' => $actor?->name,
            'email' => $actor?->email,
            'superuser' => (bool) ($actor?->is_superuser),
            'system' => $actor === null,
        ];

        $this->audit->record(
            userId: $actor?->id,
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
        $this->deleteUserAccessRows((int) $tenant->id);

        $tenantName = $tenant->name;

        // Mark complete before tenant delete: user/tenant FKs on this row are nullOnDelete.
        // Same transaction as the delete, so a constraint failure does not leave the request completed.
        $request->status = TenantPurgeStatus::Completed;
        $request->executed_at = now();
        $request->executed_by_user_id = $actor?->id;
        $request->backup_path = $backupPath;
        $request->backup_expires_at = $backupExpiresAt;
        $request->deleted_counts = $counts;
        $request->confirmation_token_hash = null;

        DB::transaction(function () use ($tenant, $request): void {
            $request->save();
            $this->deleteRestrictChildren((int) $tenant->id);
            $tenant->delete();
        });

        foreach ($adminEmails as $admin) {
            Mail::to($admin->email)->send(new TenantPurgeCompletedMail(
                tenantName: $tenantName,
                adminName: (string) $admin->name,
                counts: array_filter(
                    $counts,
                    fn ($count, $key) => ! str_starts_with((string) $key, '_') && $count > 0,
                    ARRAY_FILTER_USE_BOTH,
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

    private function assertTrialActor(TenantPurgeRequest $request, ?User $actor, ?string $password): void
    {
        if ($actor === null) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.admin_only')],
            ]);
        }

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

    private function assertPaidActor(TenantPurgeRequest $request, ?User $actor): void
    {
        if ($actor === null || ! $actor->is_superuser) {
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

    /** Systeem (niet-geverifieerde registratie) of superuser (spam) mag direct wissen. */
    private function assertUnusedActor(TenantPurgeRequest $request, ?User $actor): void
    {
        if ($actor !== null && ! $actor->is_superuser) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.superuser_only')],
            ]);
        }

        if ($request->status !== TenantPurgeStatus::Scheduled) {
            throw ValidationException::withMessages([
                'purge' => [__('subscription.purge.errors.not_scheduled')],
            ]);
        }
    }

    private function assertExpiredTrialReady(TenantPurgeRequest $request): void
    {
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
        $tenantId = (int) $tenant->id;

        foreach (self::MEDIA_PATH_COLUMNS as $table => $columns) {
            DB::table($table)
                ->where($table === 'tenants' ? 'id' : 'tenant_id', $tenantId)
                ->select(array_merge(['id'], $columns))
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($disk, $columns): void {
                    foreach ($rows as $row) {
                        foreach ($columns as $column) {
                            foreach ($this->storedPaths($column, $row->{$column}) as $path) {
                                $disk->delete($path);
                            }
                        }
                    }
                });
        }

        foreach (self::MEDIA_TENANT_DIRECTORIES as $directory) {
            $disk->deleteDirectory($directory.'/'.$tenantId);
        }
    }

    /**
     * @return list<string>
     */
    private function storedPaths(string $column, mixed $value): array
    {
        if (str_ends_with($column, '_paths') && is_string($value)) {
            $value = json_decode($value, true);
        }

        $paths = is_array($value) ? $value : [$value];

        return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * API-tokens, sessies en e-mailtokens van de gebruikers hebben geen FK naar users.
     */
    private function deleteUserAccessRows(int $tenantId): void
    {
        $users = DB::table('users')->where('tenant_id', $tenantId)->get(['id', 'email']);
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->all();
        $emails = $users->pluck('email')->filter()->map(fn ($email) => mb_strtolower(trim((string) $email)))->unique()->values()->all();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $userIds)
            ->delete();
        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email')->all())->delete();
        DB::table('email_action_tokens')->whereIn('email', $emails)->delete();
    }

    private function deleteSetNullTenantRows(int $tenantId): void
    {
        // These tables use tenant_id -> nullOnDelete; strict purge requires hard delete.
        DB::table('audit_logs')->where('tenant_id', $tenantId)->delete();
        DB::table('contact_messages')->where('tenant_id', $tenantId)->delete();
    }

    /**
     * Tenant-delete cascaded kinderen in een onbepaalde volgorde. Restrict-FK's
     * tussen die tabellen blokkeren dat (meting → indicator, meting → unit,
     * issue → indicator, unitmeting → meetveld).
     */
    private function deleteRestrictChildren(int $tenantId): void
    {
        DB::table('esg_measurements')->where('tenant_id', $tenantId)->update([
            'corrects_measurement_id' => null,
        ]);
        DB::table('esg_measurements')->where('tenant_id', $tenantId)->delete();
        DB::table('issues')->where('tenant_id', $tenantId)->whereNotNull('esg_indicator_id')->update([
            'esg_indicator_id' => null,
        ]);
        DB::table('unit_measurements')->where('tenant_id', $tenantId)->delete();
    }
}
