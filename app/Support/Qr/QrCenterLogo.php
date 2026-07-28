<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * Centrelogo voor QR-codes: organisatielogo indien aanwezig, anders WinProx.
 */
final class QrCenterLogo
{
    /** @var list<string> */
    private const WINPROX_PNG_CANDIDATES = [
        'Winprox_logo_200.png',
        'Winprox_logo_100.png',
        'Winprox_logo_300.png',
    ];

    private const WINPROX_QR_CENTER = QrLogoLayout::WINPROX_QR_CENTER_PNG;

    /** Publieke URL voor het centrelogo op scherm/print (nooit leeg). */
    public static function publicUrl(?Tenant $tenant): string
    {
        return self::tenantLogoPublicUrl($tenant) ?? self::winproxPublicUrl();
    }

    /** Organisatielogo-URL, of null wanneer geen logo is geüpload. */
    public static function tenantLogoPublicUrl(?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        $path = $tenant->logo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public static function winproxPublicUrl(): string
    {
        foreach (self::WINPROX_PNG_CANDIDATES as $file) {
            if (is_file(public_path('images/'.$file))) {
                return asset('images/'.$file);
            }
        }

        return asset(self::WINPROX_QR_CENTER);
    }

    public static function winproxAbsolutePath(): string
    {
        foreach (self::WINPROX_PNG_CANDIDATES as $file) {
            $path = public_path('images/'.$file);
            if (is_file($path)) {
                return $path;
            }
        }

        return public_path(self::WINPROX_QR_CENTER);
    }

    /** Absoluut pad voor PNG-stickerexport (organisatie of WinProx-fallback). */
    public static function absolutePath(?Tenant $tenant): string
    {
        return self::tenantLogoAbsolutePath($tenant) ?? self::winproxAbsolutePath();
    }

    /** Alleen organisatielogo op schijf — geen WinProx-fallback. */
    public static function tenantLogoAbsolutePath(?Tenant $tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        $path = $tenant->logo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        if (! is_file($absolute)) {
            return null;
        }

        return $absolute;
    }
}
