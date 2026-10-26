<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\TenantPortalBackgroundStorage;

class RemoveOrganisationShellBackgroundAction
{
    public function __construct(
        private TenantPortalBackgroundStorage $backgroundStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): Tenant
    {
        if ($tenant->shell_background_path === null || $tenant->shell_background_path === '') {
            return $tenant;
        }

        $this->backgroundStorage->delete($tenant->shell_background_path);

        return $this->updateOrganisation->handle($tenant, [
            'shell_background_path' => null,
        ], $actorUserId);
    }
}
