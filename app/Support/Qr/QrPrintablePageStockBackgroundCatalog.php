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
                'labelName' => self::humanizeBasename($filename),
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

    private static function humanizeBasename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
