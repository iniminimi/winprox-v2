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
        public bool $showTenantAddress = true,
    ) {}

    /**
     * @param  array{
     *     headerText?: ?string,
     *     tenantLogo?: ?string,
     *     showTenantAddress?: bool,
     * }  $input
     */
    public static function fromValidated(QrStickerSheetTemplate $template, array $input): self
    {
        $headerText = isset($input['headerText']) ? trim((string) $input['headerText']) : null;

        return new self(
            template: $template,
            headerText: $headerText === '' ? null : $headerText,
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($input['tenantLogo'] ?? null),
            showTenantAddress: (bool) ($input['showTenantAddress'] ?? true),
        );
    }

    public function layoutConfig(): BrandedQrStickerLayoutConfig
    {
        return new BrandedQrStickerLayoutConfig(
            tenantLogoPlacement: $this->tenantLogoPlacement,
            showTenantAddress: $this->showTenantAddress,
        );
    }

    public function isEmpty(?string $backgroundPath = null): bool
    {
        return ($this->headerText === null || $this->headerText === '')
            && ($backgroundPath === null || $backgroundPath === '')
            && $this->layoutConfig()->usesDefaults();
    }
}
