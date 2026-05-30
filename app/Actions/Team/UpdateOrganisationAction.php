<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class UpdateOrganisationAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Tenant
    {
        $updates = ['name' => $data['name']];
        if (array_key_exists('logo_path', $data)) {
            $updates['logo_path'] = $data['logo_path'];
        }

        $tenant->update($updates);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.organisation_updated',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['name' => $tenant->name, 'logo_path' => $tenant->logo_path],
        );

        return $tenant->fresh();
    }
}
