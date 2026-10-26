<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Models\Tenant;
use App\Support\TenantPortalBackground;
use App\Support\TenantPortalBackgroundStorage;
use RuntimeException;

class SetOrganisationShellStockBackgroundAction
{
    public function __construct(
        private TenantPortalBackgroundStorage $backgroundStorage,
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, string $presetKey, ?int $actorUserId = null): Tenant
    {
        $normalized = QrPrintablePageBackgroundPreset::normalizePresetKey($presetKey);
        if (! QrPrintablePageBackgroundPreset::isValidPresetKey($normalized)) {
            throw new RuntimeException('Invalid shell background preset.');
        }

        $currentPath = $tenant->shell_background_path;
        if (! TenantPortalBackground::isStockPath($currentPath)) {
            $this->backgroundStorage->delete($currentPath);
        }

        return $this->updateOrganisation->handle($tenant, [
            'shell_background_path' => $normalized,
        ], $actorUserId);
    }
}
