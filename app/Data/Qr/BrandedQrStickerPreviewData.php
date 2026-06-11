<?php

declare(strict_types=1);

namespace App\Data\Qr;

use App\Enums\QrStickerTenantLogoPlacement;

readonly class BrandedQrStickerPreviewData
{
    public function __construct(
        public ?string $headerText,
        public QrStickerTenantLogoPlacement $tenantLogoPlacement,
        public QrStickerTenantLogoPlacement $tenantAddressPlacement,
    ) {}

    public static function fromLivewireForm(
        string $headerText,
        string $tenantLogo,
        string $tenantAddress,
    ): self {
        $headerText = trim($headerText);

        return new self(
            headerText: $headerText === '' ? null : $headerText,
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($tenantLogo),
            tenantAddressPlacement: QrStickerTenantLogoPlacement::tryFromString($tenantAddress),
        );
    }

    public function showTenantAddress(): bool
    {
        return $this->tenantAddressPlacement === QrStickerTenantLogoPlacement::BottomLeft;
    }
}
