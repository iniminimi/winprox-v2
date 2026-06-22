<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use Imagick;

/** Tenant name + address in the full-width bottom band on Avery 62×89-R branded artwork. */
final class BrandedQrStickerTenantDetailsRenderer
{
    /**
     * @param  list<string>  $lines
     * @param  \GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, array $lines, QrStickerTenantLogoPlacement $logoPlacement): void
    {
        BrandedQrStickerBottomBandRenderer::drawOnGd($canvas, $lines, null, $logoPlacement);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function drawOnImagick(Imagick $canvas, array $lines, QrStickerTenantLogoPlacement $logoPlacement): void
    {
        BrandedQrStickerBottomBandRenderer::drawOnImagick($canvas, $lines, null, $logoPlacement);
    }
}
