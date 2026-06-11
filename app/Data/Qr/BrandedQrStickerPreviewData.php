<?php

declare(strict_types=1);

namespace App\Data\Qr;

use App\Enums\QrStickerTenantLogoPlacement;

readonly class BrandedQrStickerPreviewData
{
    public function __construct(
        public ?string $headerText,
        public QrStickerTenantLogoPlacement $tenantLogoPlacement,
        public bool $showTenantAddress,
    ) {}

    public static function fromLivewireForm(
        string $headerText,
        string $tenantLogo,
        bool $showTenantAddress,
    ): self {
        $headerText = trim($headerText);

        return new self(
            headerText: $headerText === '' ? null : $headerText,
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($tenantLogo),
            showTenantAddress: $showTenantAddress,
        );
    }
}
