<?php

namespace App\Support\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Genereert een QR-code als inline SVG (geen GD/Imagick nodig). Hoge
 * foutcorrectie (H, ~30%) zodat de code ook bij print/slijtage scanbaar blijft.
 */
final class QrSvg
{
    public static function svg(string $url, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd(),
        );

        $svg = (new Writer($renderer))->writeString($url, 'UTF-8', ErrorCorrectionLevel::H());

        // Strip de XML-declaratie zodat de SVG inline in HTML kan.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}
