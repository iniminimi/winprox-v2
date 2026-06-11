<?php

namespace App\Data\Team;

use App\Enums\QrStickerCenterLogoMode;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrStickerSheetTemplate;

readonly class UpdateTenantQrStickerSheetSettingsData
{
    public function __construct(
        public QrStickerSheetTemplate $template,
        public ?string $headerText,
        public QrStickerCenterLogoMode $centerLogoMode = QrStickerCenterLogoMode::Tenant,
        public bool $cornerTenantLogo = true,
        public bool $showTenantAddress = true,
    ) {}

    /**
     * @param  array{
     *     headerText?: ?string,
     *     centerLogo?: ?string,
     *     cornerTenantLogo?: bool,
     *     showTenantAddress?: bool,
     * }  $input
     */
    public static function fromValidated(QrStickerSheetTemplate $template, array $input): self
    {
        $headerText = isset($input['headerText']) ? trim((string) $input['headerText']) : null;

        return new self(
            template: $template,
            headerText: $headerText === '' ? null : $headerText,
            centerLogoMode: QrStickerCenterLogoMode::tryFromString($input['centerLogo'] ?? null),
            cornerTenantLogo: (bool) ($input['cornerTenantLogo'] ?? true),
            showTenantAddress: (bool) ($input['showTenantAddress'] ?? true),
        );
    }

    public function layoutConfig(): BrandedQrStickerLayoutConfig
    {
        return new BrandedQrStickerLayoutConfig(
            centerLogoMode: $this->centerLogoMode,
            cornerTenantLogo: $this->cornerTenantLogo,
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
