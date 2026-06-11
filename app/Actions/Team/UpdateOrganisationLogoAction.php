<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\TenantLogoStorage;
use Illuminate\Http\UploadedFile;

class UpdateOrganisationLogoAction
{
    public function __construct(
        private TenantLogoStorage $logoStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, UploadedFile $logo, ?int $actorUserId = null): Tenant
    {
        $this->logoStorage->delete($tenant->logo_path);

        return $this->updateOrganisation->handle($tenant, [
            'logo_path' => $this->logoStorage->store($logo, (int) $tenant->id),
        ], $actorUserId);
    }
}
