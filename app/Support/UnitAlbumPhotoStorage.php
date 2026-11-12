<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\UnitAlbumPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Bewaart albumfoto's op de privé-disk. De foto zelf wordt niet verkleind
 * (WINPROX_RULES.md §7). Onder het beeld komt een zwarte balk in het bestand,
 * met letters die meeschalen met de breedte.
 */
class UnitAlbumPhotoStorage
{
    public const DIRECTORY = 'unit-album';

    /** Deel van de fotobreedte. 1000 px → ongeveer 22 px lettergrootte. */
    private const STAMP_FONT_RATIO = 0.022;

    public function store(UploadedFile $file, int $tenantId, int $unitId, string $day, string $stampLine): string
    {
        $binary = $this->withBottomStamp((string) file_get_contents($file->getRealPath()), $stampLine);
        $path = self::DIRECTORY.'/'.$tenantId.'/'.$unitId.'/'.$day.'/'.Str::uuid()->toString().'.jpg';

        if (! Storage::disk(UnitAlbumPhoto::DISK)->put($path, $binary)) {
            throw new InvalidArgumentException('upload_failed');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if ($path !== '') {
            Storage::disk(UnitAlbumPhoto::DISK)->delete($path);
        }
    }

    private function withBottomStamp(string $binary, string $stampLine): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagettftext')) {
            throw new InvalidArgumentException('upload_failed');
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new InvalidArgumentException('upload_failed');
        }

        $font = public_path('fonts/Inter-Regular.ttf');
        if (! is_file($font)) {
            imagedestroy($source);
            throw new InvalidArgumentException('upload_failed');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $fontSize = max(8.0, $width * self::STAMP_FONT_RATIO);
        $maxTextWidth = max(1, (int) round($width * 0.94));
        $lines = $this->wrapStamp($stampLine, $font, $fontSize, $maxTextWidth);
        $lineHeight = (int) round($fontSize * 1.35);
        $pad = (int) round($fontSize * 0.55);
        $barHeight = max(1, $pad * 2 + $lineHeight * count($lines));

        $canvas = imagecreatetruecolor($width, $height + $barHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new InvalidArgumentException('upload_failed');
        }

        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, $height, $width - 1, $height + $barHeight - 1, $black);

        $baseline = $height + $pad + (int) round($fontSize);
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $font, $line);
            $textWidth = is_array($box) ? abs($box[2] - $box[0]) : 0;
            $x = (int) max(0, ($width - $textWidth) / 2);
            imagettftext($canvas, $fontSize, 0, $x, $baseline, $white, $font, $line);
            $baseline += $lineHeight;
        }

        ob_start();
        imagejpeg($canvas, null, 80);
        $stamped = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! is_string($stamped) || $stamped === '') {
            throw new InvalidArgumentException('upload_failed');
        }

        return $stamped;
    }

    /**
     * @return list<string>
     */
    private function wrapStamp(string $text, string $font, float $fontSize, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (! is_string($word) || $word === '') {
                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $width = is_array($box) ? abs($box[2] - $box[0]) : 0;
            if ($current !== '' && $width > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [''] : $lines;
    }
}
