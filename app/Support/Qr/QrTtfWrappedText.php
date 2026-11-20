<?php

declare(strict_types=1);

namespace App\Support\Qr;

use RuntimeException;

/**
 * Word-wrap TrueType text to a max pixel width (GD measure). Used by printable QR pages.
 */
final class QrTtfWrappedText
{
    /**
     * @return array{font_size: float, lines: list<string>}
     */
    public static function fit(
        string $text,
        string $fontPath,
        float $preferredFontSize,
        float $minFontSize,
        int $maxWidthPx,
        int $maxLines,
    ): array {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($normalized === '') {
            return ['font_size' => $preferredFontSize, 'lines' => []];
        }

        $explicit = self::explicitLines($text);
        if (count($explicit) > 1) {
            return self::fitExplicitLines($explicit, $fontPath, $preferredFontSize, $minFontSize, $maxWidthPx, $maxLines);
        }

        if (count($explicit) === 1) {
            $normalized = $explicit[0];
        }

        $preferred = max($minFontSize, $preferredFontSize);
        $min = min($preferred, max(1.0, $minFontSize));
        $step = max(0.5, ($preferred - $min) / 24);

        for ($fontSize = $preferred; $fontSize >= $min - 0.01; $fontSize -= $step) {
            $size = max($min, $fontSize);
            if (self::textWidth($fontPath, $size, $normalized) <= $maxWidthPx) {
                return ['font_size' => $size, 'lines' => [$normalized]];
            }

            $lines = self::wrapWordsUnlimited($normalized, $fontPath, $size, $maxWidthPx);
            if ($lines !== [] && count($lines) <= $maxLines && self::linesFitWidth($lines, $fontPath, $size, $maxWidthPx)) {
                return ['font_size' => $size, 'lines' => $lines];
            }
        }

        $lines = self::wrapWords($normalized, $fontPath, $min, $maxWidthPx, $maxLines);

        return [
            'font_size' => $min,
            'lines' => $lines !== [] ? $lines : [self::truncateToWidth($normalized, $fontPath, $min, $maxWidthPx)],
        ];
    }

    public static function textWidth(string $fontPath, float $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (! is_array($bbox)) {
            throw new RuntimeException('Unable to measure TrueType text width.');
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }

    public static function blockHeightPx(int $lineCount, float $fontSizePx, float $lineHeightRatio): int
    {
        if ($lineCount <= 0) {
            return 0;
        }

        $lineHeight = max($fontSizePx, $fontSizePx * $lineHeightRatio);

        return (int) ceil((($lineCount - 1) * $lineHeight) + $fontSizePx + 2);
    }

    /**
     * @return list<string>
     */
    private static function explicitLines(string $text): array
    {
        $parts = preg_split('/\R/u', $text) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $line): string => trim($line), $parts),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @param  list<string>  $lines
     * @return array{font_size: float, lines: list<string>}
     */
    private static function fitExplicitLines(
        array $lines,
        string $fontPath,
        float $preferredFontSize,
        float $minFontSize,
        int $maxWidthPx,
        int $maxLines,
    ): array {
        $lines = array_slice($lines, 0, $maxLines);
        $preferred = max($minFontSize, $preferredFontSize);
        $min = min($preferred, max(1.0, $minFontSize));
        $step = max(0.5, ($preferred - $min) / 24);

        for ($fontSize = $preferred; $fontSize >= $min - 0.01; $fontSize -= $step) {
            $size = max($min, $fontSize);
            if (self::linesFitWidth($lines, $fontPath, $size, $maxWidthPx)) {
                return ['font_size' => $size, 'lines' => $lines];
            }
        }

        $fitted = [];
        foreach ($lines as $index => $line) {
            if ($index === count($lines) - 1) {
                $fitted[] = self::truncateToWidth($line, $fontPath, $min, $maxWidthPx);
            } else {
                $fitted[] = self::textWidth($fontPath, $min, $line) <= $maxWidthPx
                    ? $line
                    : self::truncateToWidth($line, $fontPath, $min, $maxWidthPx);
            }
        }

        return ['font_size' => $min, 'lines' => $fitted];
    }

    /**
     * @return list<string>
     */
    private static function wrapWords(string $text, string $fontPath, float $fontSize, int $maxWidthPx, int $maxLines): array
    {
        $lines = self::wrapWordsUnlimited($text, $fontPath, $fontSize, $maxWidthPx);
        if ($lines === [] || count($lines) <= $maxLines) {
            return $lines;
        }

        $visible = array_slice($lines, 0, $maxLines - 1);
        $overflow = implode(' ', array_slice($lines, $maxLines - 1));
        $visible[] = self::truncateToWidth($overflow, $fontPath, $fontSize, $maxWidthPx);

        return $visible;
    }

    /**
     * @return list<string>
     */
    private static function wrapWordsUnlimited(string $text, string $fontPath, float $fontSize, int $maxWidthPx): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (self::textWidth($fontPath, $fontSize, $candidate) <= $maxWidthPx) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = $word;

                continue;
            }

            $lines[] = self::truncateToWidth($word, $fontPath, $fontSize, $maxWidthPx);
            $current = '';
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function linesFitWidth(array $lines, string $fontPath, float $fontSize, int $maxWidthPx): bool
    {
        foreach ($lines as $line) {
            if (self::textWidth($fontPath, $fontSize, $line) > $maxWidthPx) {
                return false;
            }
        }

        return true;
    }

    private static function truncateToWidth(string $text, string $fontPath, float $fontSize, int $maxWidthPx): string
    {
        $ellipsis = '…';
        if (self::textWidth($fontPath, $fontSize, $text) <= $maxWidthPx) {
            return $text;
        }

        $length = mb_strlen($text);
        while ($length > 0) {
            $candidate = rtrim(mb_substr($text, 0, $length)).$ellipsis;
            if (self::textWidth($fontPath, $fontSize, $candidate) <= $maxWidthPx) {
                return $candidate;
            }
            $length--;
        }

        return $ellipsis;
    }
}
