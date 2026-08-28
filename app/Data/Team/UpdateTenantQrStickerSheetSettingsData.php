<?php

namespace App\Data\Team;

use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrStickerSheetTemplate;

readonly class UpdateTenantQrStickerSheetSettingsData
{
    public function __construct(
        public QrStickerSheetTemplate $template,
        public ?string $headerText,
        public QrStickerTenantLogoPlacement $tenantLogoPlacement = QrStickerTenantLogoPlacement::BottomRight,
        public QrStickerTenantLogoPlacement $tenantAddressPlacement = QrStickerTenantLogoPlacement::BottomLeft,
    ) {}

    /**
     * @param  array{
     *     headerText?: ?string,
     *     tenantLogo?: ?string,
     *     tenantAddress?: ?string,
     * }  $input
     */
    public static function fromValidated(QrStickerSheetTemplate $template, array $input): self
    {
        $headerText = isset($input['headerText']) ? trim((string) $input['headerText']) : null;

        return new self(
            template: $template,
            headerText: $headerText === '' ? null : $headerText,
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($input['tenantLogo'] ?? null),
            tenantAddressPlacement: QrStickerTenantLogoPlacement::tryFromStringForAddress($input['tenantAddress'] ?? null),
        );
    }

    public function layoutConfig(): BrandedQrStickerLayoutConfig
    {
        return new BrandedQrStickerLayoutConfig(
            tenantLogoPlacement: $this->tenantLogoPlacement,
            tenantAddressPlacement: $this->tenantAddressPlacement,
        );
    }

    public function isEmpty(?string $backgroundPath = null): bool
    {
        return ($this->headerText === null || $this->headerText === '')
            && ($backgroundPath === null || $backgroundPath === '')
            && $this->layoutConfig()->usesDefaults();
    }
}
