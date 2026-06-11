<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\TenantPortalBackgroundStorage;
use Illuminate\Http\UploadedFile;

class UpdateOrganisationPortalBackgroundAction
{
    public function __construct(
        private TenantPortalBackgroundStorage $backgroundStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, UploadedFile $background, ?int $actorUserId = null): Tenant
    {
        $this->backgroundStorage->delete($tenant->portal_background_path);

        return $this->updateOrganisation->handle($tenant, [
            'portal_background_path' => $this->backgroundStorage->store($background, (int) $tenant->id),
        ], $actorUserId);
    }
}
