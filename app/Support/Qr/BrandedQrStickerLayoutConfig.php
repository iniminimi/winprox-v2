<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use App\Models\TenantQrStickerSheetSetting;

/** Parsed layout_config for branded QR sticker export. */
final class BrandedQrStickerLayoutConfig
{
    public function __construct(
        private readonly QrStickerTenantLogoPlacement $tenantLogoPlacement,
        private readonly bool $showTenantAddress,
    ) {}

    public static function fromSetting(?TenantQrStickerSheetSetting $setting): self
    {
        $config = is_array($setting?->layout_config) ? $setting->layout_config : [];

        return new self(
            tenantLogoPlacement: self::resolveTenantLogoPlacement($config),
            showTenantAddress: (bool) ($config['tenant_address'] ?? true),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function resolveTenantLogoPlacement(array $config): QrStickerTenantLogoPlacement
    {
        if (array_key_exists('tenant_logo', $config)) {
            return QrStickerTenantLogoPlacement::tryFromString(
                is_string($config['tenant_logo'] ?? null) ? $config['tenant_logo'] : null,
            );
        }

        if (array_key_exists('corner_tenant_logo', $config) && $config['corner_tenant_logo'] === false) {
            return QrStickerTenantLogoPlacement::None;
        }

        return QrStickerTenantLogoPlacement::default();
    }

    /**
     * @return array{tenant_logo: string, tenant_address: bool}
     */
    public function toArray(): array
    {
        return [
            'tenant_logo' => $this->tenantLogoPlacement->value,
            'tenant_address' => $this->showTenantAddress,
        ];
    }

    public function usesDefaults(): bool
    {
        return $this->tenantLogoPlacement === QrStickerTenantLogoPlacement::default()
            && $this->showTenantAddress;
    }

    public function tenantLogoPlacement(): QrStickerTenantLogoPlacement
    {
        return $this->tenantLogoPlacement;
    }

    public function showTenantLogoOnSticker(): bool
    {
        return $this->tenantLogoPlacement !== QrStickerTenantLogoPlacement::None;
    }

    public function showTenantAddress(): bool
    {
        return $this->showTenantAddress;
    }
}
