<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Qr\QrPrintablePageStockBackgroundCatalog;
use Illuminate\Support\Facades\Storage;

final class TenantPortalBackground
{
    public static function isStockPath(?string $path): bool
    {
        return is_string($path)
            && $path !== ''
            && QrPrintablePageStockBackgroundCatalog::isStockPresetKey($path);
    }

    public static function stockPresetKeyFromPath(?string $path): ?string
    {
        if (! self::isStockPath($path)) {
            return null;
        }

        return QrPrintablePageStockBackgroundCatalog::findByPresetKey($path) !== null
            ? $path
            : null;
    }

    /**
     * Pad dat rechtstreeks onder public/ staat (bv. images/svg/…), verspreid
     * via git i.p.v. via storage — veilige basename + images/-prefix vereist.
     */
    public static function isPublicAssetPath(?string $path): bool
    {
        if (! is_string($path) || ! str_starts_with($path, 'images/')) {
            return false;
        }

        if ($path !== 'images/'.basename($path) && ! str_starts_with($path, 'images/svg/')) {
            return false;
        }

        return is_file(public_path($path));
    }

    /**
     * Achtergronden onder public/images/svg/ — via git op elke omgeving
     * aanwezig, selecteerbaar als preset.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function shellAssetChoices(): array
    {
        $directory = public_path('images/svg');
        if (! is_dir($directory)) {
            return [];
        }

        $choices = [];
        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename !== basename($filename) || ! is_file($directory.DIRECTORY_SEPARATOR.$filename)) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (! in_array($extension, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true)) {
                continue;
            }

            $choices[] = [
                'value' => 'images/svg/'.$filename,
                'label' => pathinfo($filename, PATHINFO_FILENAME),
            ];
        }

        usort($choices, fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $choices;
    }

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (self::isStockPath($path)) {
            return QrPrintablePageStockBackgroundCatalog::findByPresetKey($path)['publicUrl'] ?? null;
        }

        if (self::isPublicAssetPath($path)) {
            return asset($path);
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
