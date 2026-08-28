<?php

declare(strict_types=1);

namespace App\Data\Qr;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\BrandedQrStickerLayoutConfig;

readonly class BrandedQrPrintablePagePreviewData
{
    public function __construct(
        public string $presetKey,
        public QrStickerTenantLogoPlacement $tenantLogoPlacement,
        public QrStickerTenantLogoPlacement $tenantAddressPlacement,
    ) {}

    public static function fromLivewireForm(
        string $preset,
        string $tenantLogo,
        string $tenantAddress,
    ): self {
        $presetKey = QrPrintablePageBackgroundPreset::isValidPresetKey($preset)
            ? $preset
            : QrPrintablePageBackgroundPreset::default()->value;

        return new self(
            presetKey: $presetKey,
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($tenantLogo),
            tenantAddressPlacement: QrStickerTenantLogoPlacement::tryFromString($tenantAddress),
        );
    }

    public function brandingLayout(): BrandedQrStickerLayoutConfig
    {
        return new BrandedQrStickerLayoutConfig(
            tenantLogoPlacement: $this->tenantLogoPlacement,
            tenantAddressPlacement: $this->tenantAddressPlacement,
        );
    }

    /**
     * Ephemeral layout_config for preview: live preset + logo/address placements.
     *
     * @return array<string, string>
     */
    public function layoutConfigForPreview(): array
    {
        return array_merge(
            [QrPrintablePageBackgroundPreset::LAYOUT_KEY => $this->presetKey],
            $this->brandingLayout()->toArray(),
        );
    }
}
