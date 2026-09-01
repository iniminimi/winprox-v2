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

    /** @var array<string, string> */
    private const LEGACY_STOCK_FILES = [
        self::Blue->value => 'back_07.jpg',
        self::Green->value => 'back_08.jpg',
        self::Multi->value => 'back_09.jpg',
    ];

    public static function defaultPresetKey(): string
    {
        return QrPrintablePageStockBackgroundCatalog::defaultPresetKey();
    }

    public static function normalizePresetKey(string $presetKey): string
    {
        $legacyFile = self::LEGACY_STOCK_FILES[$presetKey] ?? null;
        if ($legacyFile !== null) {
            return QrPrintablePageStockBackgroundCatalog::PRESET_PREFIX.$legacyFile;
        }

        return $presetKey;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function uiChoices(): array
    {
        $choices = [];

        foreach (QrPrintablePageStockBackgroundCatalog::entries() as $entry) {
            $choices[] = [
                'value' => $entry['presetKey'],
                'label' => $entry['labelName'],
            ];
        }

        return $choices;
    }

    /**
     * @return list<string>
     */
    public static function uiChoiceValues(): array
    {
        return array_map(
            static fn (array $choice): string => $choice['value'],
            self::uiChoices(),
        );
    }

    public static function isValidPresetKey(string $presetKey): bool
    {
        return QrPrintablePageStockBackgroundCatalog::findByPresetKey(
            self::normalizePresetKey($presetKey),
        ) !== null;
    }

    public static function presetKeyFromSetting(?TenantQrStickerSheetSetting $setting): string
    {
        $config = $setting?->layout_config;
        if (! is_array($config)) {
            return self::defaultPresetKey();
        }

        $value = $config[self::LAYOUT_KEY] ?? null;
        if (! is_string($value) || $value === '') {
            return self::defaultPresetKey();
        }

        $normalized = self::normalizePresetKey($value);

        return self::isValidPresetKey($normalized) ? $normalized : self::defaultPresetKey();
    }
}
