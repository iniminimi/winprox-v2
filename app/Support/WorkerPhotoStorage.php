<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Slaat reeds (client-side) gecomprimeerde worker-foto's op zonder backend-resize.
 * Conform WINPROX_RULES.md §7: geen Imagick/GD-resize op de server.
 */
class WorkerPhotoStorage
{
    /**
     * Bewaart de al-gecomprimeerde upload op de publieke disk en geeft het
     * relatieve pad terug (bv. "worker-photos/abc.jpg").
     */
    public function storePrecompressedCopy(UploadedFile $file): string
    {
        return $file->store('worker-photos', 'public');
    }
}
