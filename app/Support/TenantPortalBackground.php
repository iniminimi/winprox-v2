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

    public static function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (self::isStockPath($path)) {
            return QrPrintablePageStockBackgroundCatalog::findByPresetKey($path)['publicUrl'] ?? null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
