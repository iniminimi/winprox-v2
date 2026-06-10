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
            'name' => $tenant->name,
            'custom_theme_active' => $tenant->custom_theme_active,
            'custom_theme_bg' => $tenant->custom_theme_bg,
            'custom_theme_btn' => $tenant->custom_theme_btn,
            'logo_path' => $this->logoStorage->store($logo, (int) $tenant->id),
        ], $actorUserId);
    }
}
