<?php

namespace App\Actions\Portal;

use App\Enums\WorkerIdentityStatus;
use App\Models\Worker;
use App\Support\Portal\WorkerIcon;

class ResolveWorkerIdentityForTenantAction
{
    /**
     * @return array{status: WorkerIdentityStatus, worker?: Worker}
     */
    public function handle(int $tenantId, string $firstName, string $lastName): array
    {
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim($lastName));
        if ($first === '' || $last === '') {
            return ['status' => WorkerIdentityStatus::NotFound];
        }

        $matches = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Worker $worker) => mb_strtolower(trim((string) $worker->first_name)) === $first
                && mb_strtolower(trim((string) $worker->last_name)) === $last)
            ->values();

        if ($matches->count() === 0) {
            return ['status' => WorkerIdentityStatus::NotFound];
        }

        if ($matches->count() > 1) {
            return ['status' => WorkerIdentityStatus::Ambiguous];
        }

        $worker = $matches->first();
        $iconSlug = trim((string) $worker->field_icon_slug);
        if ($iconSlug === '' || ! WorkerIcon::isValidSlug($iconSlug)) {
            return ['status' => WorkerIdentityStatus::Claimable, 'worker' => $worker];
        }

        return ['status' => WorkerIdentityStatus::Found, 'worker' => $worker];
    }
}
