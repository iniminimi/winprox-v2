<?php

namespace App\Actions\Retention;

use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Controleert het pad en noteert de download. De policy beslist of het mag. */
final class OpenTenantTimeArchiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, User $actor, TenantTimeArchive $archive): string
    {
        $path = (string) $archive->path;
        $prefix = TenantTimeArchive::DIRECTORY.'/'.$tenant->id.'/';
        if ((int) $archive->tenant_id !== (int) $tenant->id
            || ! str_starts_with($path, $prefix)
            || str_contains($path, '..')
            || $archive->available_until === null
            || $archive->available_until->lte(now())
            || ! Storage::disk(TenantTimeArchive::DISK)->exists($path)) {
            throw new NotFoundHttpException;
        }

        $this->audit->record(
            userId: (int) $actor->id,
            tenantId: (int) $tenant->id,
            action: 'time_archive.download',
            modelType: TenantTimeArchive::class,
            modelId: (int) $archive->id,
            payload: [
                'kind' => $archive->kind,
                'period_key' => $archive->period_key,
            ],
        );

        return $path;
    }
}
