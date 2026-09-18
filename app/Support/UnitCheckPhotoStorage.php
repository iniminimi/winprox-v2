<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Slaat reeds (client-side) gecomprimeerde unit-check-foto's op zonder backend-resize.
 * Conform WINPROX_RULES.md §7: geen Imagick/GD-resize op de server.
 */
class UnitCheckPhotoStorage
{
    /**
     * Bewaart de al-gecomprimeerde upload op de publieke disk en geeft het
     * relatieve pad terug (bv. "unit-check-photos/abc.jpg").
     */
    public function storePrecompressedCopy(UploadedFile $file): string
    {
        return $file->store('unit-check-photos', 'public');
    }
}
