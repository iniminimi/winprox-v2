<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrPrintablePageStockBackgroundCatalog;

enum QrPrintablePageBackgroundPreset: string
{
    case Blue = 'blue';

    case Green = 'green';

    case Multi = 'multi';

    public const LAYOUT_KEY = 'background_preset';

    /**
     * GD-friendly raster (outside images/qr/svg — that folder keeps SVG sources only).
     */
    public function relativePng(): string
    {
        return match ($this) {
            self::Blue => 'images/qr/QR_printable_blue.png',
            self::Green => 'images/qr/QR_printable_green.png',
            self::Multi => 'images/qr/QR_printable_multi_color.png',
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

        throw new \RuntimeException('Printable QR page preset background is missing at '.$this->relativeSvg().'.');
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

    /**
     * @return list<array{value: string, labelKey: string, labelParams?: array<string, string>}>
     */
    public static function uiChoices(): array
    {
        $choices = [];

        foreach (self::cases() as $preset) {
            $choices[] = [
                'value' => $preset->value,
                'labelKey' => 'settings.qr_stickers.printable_page.preset_'.$preset->value,
            ];
        }

        foreach (QrPrintablePageStockBackgroundCatalog::entries() as $entry) {
            $choices[] = [
                'value' => $entry['presetKey'],
                'labelKey' => 'settings.qr_stickers.printable_page.preset_stock',
                'labelParams' => ['name' => $entry['labelName']],
            ];
        }

        return $choices;
    }

    public static function isValidPresetKey(string $presetKey): bool
    {
        return self::tryFrom($presetKey) !== null
            || QrPrintablePageStockBackgroundCatalog::findByPresetKey($presetKey) !== null;
    }

    public static function presetKeyFromSetting(?TenantQrStickerSheetSetting $setting): string
    {
        $config = $setting?->layout_config;
        if (! is_array($config)) {
            return self::default()->value;
        }

        $value = $config[self::LAYOUT_KEY] ?? null;
        if (! is_string($value) || $value === '') {
            return self::default()->value;
        }

        return self::isValidPresetKey($value) ? $value : self::default()->value;
    }

    public static function fromSetting(?TenantQrStickerSheetSetting $setting): self
    {
        return self::tryFrom(self::presetKeyFromSetting($setting)) ?? self::default();
    }
}
