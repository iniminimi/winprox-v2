<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\TenantPortalBackgroundStorage;
use Illuminate\Http\UploadedFile;

class UpdateOrganisationShellBackgroundAction
{
    public function __construct(
        private TenantPortalBackgroundStorage $backgroundStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, UploadedFile $background, ?int $actorUserId = null): Tenant
    {
        $this->backgroundStorage->delete($tenant->shell_background_path);
        $path = $this->backgroundStorage->store($background, (int) $tenant->id);

        return $this->updateOrganisation->handle($tenant, [
            'shell_background_path' => $path,
        ], $actorUserId);
    }
}
