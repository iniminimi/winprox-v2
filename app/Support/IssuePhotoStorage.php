<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Slaat reeds (client-side) gecomprimeerde foto's op zonder backend-resize.
 * Conform WINPROX_RULES.md §7: geen Imagick/GD-resize op de server.
 */
class IssuePhotoStorage
{
    /**
     * Bewaart de al-gecomprimeerde upload op de publieke disk en geeft het
     * relatieve pad terug (bv. "issue-photos/abc.jpg").
     */
    public function storePrecompressedCopy(UploadedFile $file): string
    {
        return $file->store('issue-photos', 'public');
    }
}
