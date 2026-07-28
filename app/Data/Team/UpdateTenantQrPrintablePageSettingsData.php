<?php

declare(strict_types=1);

namespace App\Data\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\BrandedQrStickerLayoutConfig;

readonly class UpdateTenantQrPrintablePageSettingsData
{
    public function __construct(
        public QrPrintablePageBackgroundPreset $preset,
        public QrStickerTenantLogoPlacement $tenantLogoPlacement = QrStickerTenantLogoPlacement::BottomRight,
        public QrStickerTenantLogoPlacement $tenantAddressPlacement = QrStickerTenantLogoPlacement::BottomLeft,
    ) {}

    /**
     * @param  array{
     *     preset?: string,
     *     tenantLogo?: string,
     *     tenantAddress?: string,
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            preset: QrPrintablePageBackgroundPreset::tryFrom((string) ($input['preset'] ?? ''))
                ?? QrPrintablePageBackgroundPreset::default(),
            tenantLogoPlacement: QrStickerTenantLogoPlacement::tryFromString($input['tenantLogo'] ?? null),
            tenantAddressPlacement: QrStickerTenantLogoPlacement::tryFromString($input['tenantAddress'] ?? null),
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
     * @return array<string, string>|null
     */
    public function layoutConfig(): ?array
    {
        $config = [];

        if ($this->preset !== QrPrintablePageBackgroundPreset::default()) {
            $config[QrPrintablePageBackgroundPreset::LAYOUT_KEY] = $this->preset->value;
        }

        $branding = $this->brandingLayout();
        if (! $branding->usesDefaults()) {
            $config = array_merge($config, $branding->toArray());
        }

        return $config === [] ? null : $config;
    }

    public function isEmpty(?string $backgroundPath = null): bool
    {
        return $this->preset === QrPrintablePageBackgroundPreset::default()
            && ($backgroundPath === null || $backgroundPath === '')
            && $this->brandingLayout()->usesDefaults();
    }
}
