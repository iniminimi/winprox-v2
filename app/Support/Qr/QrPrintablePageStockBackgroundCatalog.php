<?php

declare(strict_types=1);

namespace App\Support\Qr;

/**
 * Stock backgrounds shipped in public/images/qr/background (discovered at runtime).
 */
final class QrPrintablePageStockBackgroundCatalog
{
    public const RELATIVE_DIRECTORY = 'images/qr/background';

    public const PRESET_PREFIX = 'stock:';

    /** @var list<array{presetKey: string, basename: string, relativePath: string, absolutePath: string, publicUrl: string, labelName: string}>|null */
    private static ?array $entries = null;

    public static function defaultPresetKey(): string
    {
        $entries = self::entries();

        return $entries[0]['presetKey'] ?? self::PRESET_PREFIX.'back_01.jpg';
    }

    /**
     * @return list<string>
     */
    public static function presetKeys(): array
    {
        return array_map(
            static fn (array $entry): string => $entry['presetKey'],
            self::entries(),
        );
    }

    /**
     * @return list<array{presetKey: string, basename: string, relativePath: string, absolutePath: string, publicUrl: string, labelName: string}>
     */
    public static function entries(): array
    {
        if (self::$entries !== null) {
            return self::$entries;
        }

        $directory = public_path(self::RELATIVE_DIRECTORY);
        if (! is_dir($directory)) {
            return self::$entries = [];
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $entries = [];

        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename === '.' || $filename === '..') {
                continue;
            }

            if ($filename !== basename($filename)) {
                continue;
            }

            $absolutePath = $directory.DIRECTORY_SEPARATOR.$filename;
            if (! is_file($absolutePath)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (! in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $relativePath = self::RELATIVE_DIRECTORY.'/'.$filename;
            $entries[] = [
                'presetKey' => self::PRESET_PREFIX.$filename,
                'basename' => $filename,
                'relativePath' => $relativePath,
                'absolutePath' => $absolutePath,
                'publicUrl' => asset($relativePath),
                'labelName' => self::displayLabel($filename),
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => strnatcasecmp($left['basename'], $right['basename']),
        );

        return self::$entries = $entries;
    }

    /**
     * @return array{presetKey: string, basename: string, relativePath: string, absolutePath: string, publicUrl: string, labelName: string}|null
     */
    public static function findByPresetKey(string $presetKey): ?array
    {
        foreach (self::entries() as $entry) {
            if ($entry['presetKey'] === $presetKey) {
                return $entry;
            }
        }

        return null;
    }

    public static function isStockPresetKey(string $presetKey): bool
    {
        return str_starts_with($presetKey, self::PRESET_PREFIX);
    }

    public static function absolutePathForPresetKey(string $presetKey): ?string
    {
        return self::findByPresetKey($presetKey)['absolutePath'] ?? null;
    }

    public static function resetCacheForTesting(): void
    {
        self::$entries = null;
    }

    private static function displayLabel(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        if (preg_match('/(\d+)/', $stem, $matches) === 1) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        return $stem;
    }
}
