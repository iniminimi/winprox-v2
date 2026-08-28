<?php

declare(strict_types=1);

namespace App\Data\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\BrandedQrStickerLayoutConfig;

readonly class UpdateTenantQrPrintablePageSettingsData
{
    public function __construct(
        public string $presetKey,
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
        $presetKey = (string) ($input['preset'] ?? '');
        if (! QrPrintablePageBackgroundPreset::isValidPresetKey($presetKey)) {
            $presetKey = QrPrintablePageBackgroundPreset::defaultPresetKey();
        } else {
            $presetKey = QrPrintablePageBackgroundPreset::normalizePresetKey($presetKey);
        }

        return new self(
            presetKey: $presetKey,
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

        if ($this->presetKey !== QrPrintablePageBackgroundPreset::defaultPresetKey()) {
            $config[QrPrintablePageBackgroundPreset::LAYOUT_KEY] = $this->presetKey;
        }

        $branding = $this->brandingLayout();
        if (! $branding->usesDefaults()) {
            $config = array_merge($config, $branding->toArray());
        }

        return $config === [] ? null : $config;
    }

    public function isEmpty(?string $backgroundPath = null): bool
    {
        return $this->presetKey === QrPrintablePageBackgroundPreset::defaultPresetKey()
            && ($backgroundPath === null || $backgroundPath === '')
            && $this->brandingLayout()->usesDefaults();
    }
}
