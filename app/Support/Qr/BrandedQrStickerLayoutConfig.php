<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerCenterLogoMode;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;

/** Parsed layout_config for branded QR sticker export. */
final class BrandedQrStickerLayoutConfig
{
    public function __construct(
        private readonly QrStickerCenterLogoMode $centerLogoMode,
        private readonly bool $cornerTenantLogo,
        private readonly bool $showTenantAddress,
    ) {}

    public static function fromSetting(?TenantQrStickerSheetSetting $setting): self
    {
        $config = is_array($setting?->layout_config) ? $setting->layout_config : [];

        return new self(
            centerLogoMode: QrStickerCenterLogoMode::tryFromString($config['center_logo'] ?? null),
            cornerTenantLogo: (bool) ($config['corner_tenant_logo'] ?? true),
            showTenantAddress: (bool) ($config['tenant_address'] ?? true),
        );
    }

    /**
     * @return array{center_logo: string, corner_tenant_logo: bool, tenant_address: bool}
     */
    public function toArray(): array
    {
        return [
            'center_logo' => $this->centerLogoMode->value,
            'corner_tenant_logo' => $this->cornerTenantLogo,
            'tenant_address' => $this->showTenantAddress,
        ];
    }

    public function usesDefaults(): bool
    {
        return $this->centerLogoMode === QrStickerCenterLogoMode::default()
            && $this->cornerTenantLogo
            && $this->showTenantAddress;
    }

    public function centerLogoMode(): QrStickerCenterLogoMode
    {
        return $this->centerLogoMode;
    }

    public function showCornerTenantLogo(): bool
    {
        return $this->cornerTenantLogo;
    }

    public function showTenantAddress(): bool
    {
        return $this->showTenantAddress;
    }

    public function includeCenterLogo(): bool
    {
        return $this->centerLogoMode !== QrStickerCenterLogoMode::None;
    }

    public function resolveCenterLogoPath(?Tenant $tenant): ?string
    {
        return match ($this->centerLogoMode) {
            QrStickerCenterLogoMode::None => null,
            QrStickerCenterLogoMode::Winprox => QrCenterLogo::winproxAbsolutePath(),
            QrStickerCenterLogoMode::Tenant => QrCenterLogo::absolutePath($tenant),
        };
    }
}
