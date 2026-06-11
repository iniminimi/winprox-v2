<?php

declare(strict_types=1);

namespace App\Support\Qr;

use GdImage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Renders unit / sticker label in the Avery 62×89-R header band (white on black artwork).
 */
final class BrandedQrStickerHeaderRenderer
{
    /**
     * @param  GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $layout = self::layout($text, $font);

        [$r, $g, $b] = Avery62x89StickerArtworkLayout::HEADER_COLOR_RGB;
        $color = imagecolorallocate($canvas, $r, $g, $b);
        if ($color === false) {
            throw new \RuntimeException('Unable to allocate branded sticker header text color.');
        }

        $x = Avery62x89StickerArtworkLayout::HEADER_PADDING_LEFT_PX;
        $y = Avery62x89StickerArtworkLayout::HEADER_TOP_PX;

        foreach ($layout['lines'] as $line) {
            $bbox = imagettfbbox($layout['font_size'], 0, $font, $line);
            if (! is_array($bbox)) {
                throw new \RuntimeException('Unable to measure branded sticker header text.');
            }

            $ascent = abs($bbox[7]);
            imagettftext(
                $canvas,
                $layout['font_size'],
                0,
                $x,
                $y + $ascent,
                $color,
                $font,
                $line,
            );

            $y += (int) round($layout['font_size'] * Avery62x89StickerArtworkLayout::HEADER_LINE_HEIGHT_RATIO);
        }
    }

    public static function drawOnImagick(Imagick $canvas, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $layout = self::layout($text, $font);

        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize((float) $layout['font_size']);
        $draw->setFillColor(new ImagickPixel('white'));
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);

        $x = (float) Avery62x89StickerArtworkLayout::HEADER_PADDING_LEFT_PX;
        $y = (float) Avery62x89StickerArtworkLayout::HEADER_TOP_PX;
        $lineHeight = $layout['font_size'] * Avery62x89StickerArtworkLayout::HEADER_LINE_HEIGHT_RATIO;

        foreach ($layout['lines'] as $line) {
            $metrics = $canvas->queryFontMetrics($draw, $line);
            $ascent = is_array($metrics) ? (float) ($metrics['ascender'] ?? $layout['font_size']) : (float) $layout['font_size'];
            $canvas->annotateImage($draw, $x, $y + $ascent, 0, $line);
            $y += $lineHeight;
        }
    }

    /**
     * @return array{font_size: int, lines: list<string>}
     */
    private static function layout(string $text, string $fontPath): array
    {
        $maxWidth = Avery62x89StickerArtworkLayout::headerMaxWidthPx();
        $maxLines = Avery62x89StickerArtworkLayout::HEADER_MAX_LINES;

        for ($fontSize = Avery62x89StickerArtworkLayout::HEADER_MAX_FONT_SIZE_PX;
            $fontSize >= Avery62x89StickerArtworkLayout::HEADER_MIN_FONT_SIZE_PX;
            $fontSize--) {
            $singleLineWidth = self::textWidth($fontPath, $fontSize, $text);
            if ($singleLineWidth <= $maxWidth) {
                return ['font_size' => $fontSize, 'lines' => [$text]];
            }

            $lines = self::wrapWords($text, $fontPath, $fontSize, $maxWidth, $maxLines);
            if ($lines !== [] && self::linesFit($lines, $fontPath, $fontSize, $maxWidth)) {
                return ['font_size' => $fontSize, 'lines' => $lines];
            }
        }

        $fontSize = Avery62x89StickerArtworkLayout::HEADER_MIN_FONT_SIZE_PX;

        return [
            'font_size' => $fontSize,
            'lines' => [self::truncateToWidth($text, $fontPath, $fontSize, $maxWidth)],
        ];
    }

    /**
     * @return list<string>
     */
    private static function wrapWords(string $text, string $fontPath, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (self::textWidth($fontPath, $fontSize, $candidate) <= $maxWidth) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                if (count($lines) >= $maxLines) {
                    return self::truncateWrappedLines($lines, $fontPath, $fontSize, $maxWidth, $maxLines);
                }
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            return self::truncateWrappedLines(array_slice($lines, 0, $maxLines - 1), $fontPath, $fontSize, $maxWidth, $maxLines)
                ?: [self::truncateToWidth($text, $fontPath, $fontSize, $maxWidth)];
        }

        return $lines;
    }

    /**
     * @param  list<string>  $prefixLines
     * @return list<string>
     */
    private static function truncateWrappedLines(array $prefixLines, string $fontPath, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $lastLine = $prefixLines[count($prefixLines) - 1] ?? '';
        $prefixLines[count($prefixLines) - 1] = self::truncateToWidth($lastLine, $fontPath, $fontSize, $maxWidth);

        return array_slice($prefixLines, 0, $maxLines);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function linesFit(array $lines, string $fontPath, int $fontSize, int $maxWidth): bool
    {
        foreach ($lines as $line) {
            if (self::textWidth($fontPath, $fontSize, $line) > $maxWidth) {
                return false;
            }
        }

        $lineHeight = $fontSize * Avery62x89StickerArtworkLayout::HEADER_LINE_HEIGHT_RATIO;

        return ($lineHeight * count($lines)) <= Avery62x89StickerArtworkLayout::HEADER_MAX_HEIGHT_PX;
    }

    private static function truncateToWidth(string $text, string $fontPath, int $fontSize, int $maxWidth): string
    {
        $ellipsis = '…';
        if (self::textWidth($fontPath, $fontSize, $text) <= $maxWidth) {
            return $text;
        }

        $length = mb_strlen($text);
        while ($length > 0) {
            $candidate = rtrim(mb_substr($text, 0, $length)).$ellipsis;
            if (self::textWidth($fontPath, $fontSize, $candidate) <= $maxWidth) {
                return $candidate;
            }
            $length--;
        }

        return $ellipsis;
    }

    private static function textWidth(string $fontPath, int $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (! is_array($bbox)) {
            throw new \RuntimeException('Unable to measure branded sticker header text width.');
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }
}
