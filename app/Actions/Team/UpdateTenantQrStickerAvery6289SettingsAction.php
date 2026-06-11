<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Support\Qr\BrandedQrStickerHeaderText;

class UpdateTenantQrStickerAvery6289SettingsAction
{
    public function __construct(
        private UpdateOrganisationAction $updateOrganisation,
    ) {}

    public function handle(Tenant $tenant, ?string $headerText, ?int $actorUserId = null): Tenant
    {
        $normalized = $headerText === null ? null : BrandedQrStickerHeaderText::fitForSticker($headerText);

        return $this->updateOrganisation->handle($tenant, [
            'name' => $tenant->name,
            'custom_theme_active' => $tenant->custom_theme_active,
            'custom_theme_bg' => $tenant->custom_theme_bg,
            'custom_theme_btn' => $tenant->custom_theme_btn,
            'qr_sticker_avery_62x89_header_text' => $normalized === '' ? null : $normalized,
        ], $actorUserId);
    }
}
