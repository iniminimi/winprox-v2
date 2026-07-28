<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\TenantQrStickerSheetSetting;

enum QrPrintablePageBackgroundPreset: string
{
    case Blue = 'blue';

    case Green = 'green';

    case Multi = 'multi';

    public const LAYOUT_KEY = 'background_preset';

    public function relativePng(): string
    {
        return match ($this) {
            self::Blue => 'images/qr/svg/QR_printable_blue.png',
            self::Green => 'images/qr/svg/QR_printable_green.png',
            self::Multi => 'images/qr/svg/QR_printable_multi_color.png',
        };
    }

    public function relativeSvg(): string
    {
        return match ($this) {
            self::Blue => 'images/qr/svg/QR_printable_blue.svg',
            self::Green => 'images/qr/svg/QR_printable_green.svg',
            self::Multi => 'images/qr/svg/QR_printable_multi_color.svg',
        };
    }

    public function absolutePath(): string
    {
        $png = public_path($this->relativePng());
        if (is_file($png)) {
            return $png;
        }

        $svg = public_path($this->relativeSvg());
        if (is_file($svg)) {
            return $svg;
        }

        throw new \RuntimeException('Printable QR page preset background is missing at '.$this->relativePng().'.');
    }

    public function publicUrl(): string
    {
        $png = public_path($this->relativePng());
        if (is_file($png)) {
            return asset($this->relativePng());
        }

        return asset($this->relativeSvg());
    }

    public static function default(): self
    {
        return self::Blue;
    }

    /**
     * @return list<self>
     */
    public static function choices(): array
    {
        return self::cases();
    }

    public static function fromSetting(?TenantQrStickerSheetSetting $setting): self
    {
        $config = $setting?->layout_config;
        if (! is_array($config)) {
            return self::default();
        }

        $value = $config[self::LAYOUT_KEY] ?? null;

        return self::tryFrom(is_string($value) ? $value : '') ?? self::default();
    }
}
