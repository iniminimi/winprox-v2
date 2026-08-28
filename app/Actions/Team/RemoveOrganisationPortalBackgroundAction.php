<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\TenantPortalBackgroundStorage;

class RemoveOrganisationPortalBackgroundAction
{
    public function __construct(
        private TenantPortalBackgroundStorage $backgroundStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): Tenant
    {
        if ($tenant->portal_background_path === null || $tenant->portal_background_path === '') {
            return $tenant->fresh();
        }

        $this->backgroundStorage->delete($tenant->portal_background_path);

        return $this->updateOrganisation->handle($tenant, [
            'portal_background_path' => null,
        ], $actorUserId);
    }
}
