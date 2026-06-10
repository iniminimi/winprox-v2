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
            'name' => $tenant->name,
            'custom_theme_active' => $tenant->custom_theme_active,
            'custom_theme_bg' => $tenant->custom_theme_bg,
            'custom_theme_btn' => $tenant->custom_theme_btn,
            'portal_background_path' => $this->backgroundStorage->store($background, (int) $tenant->id),
        ], $actorUserId);
    }
}
