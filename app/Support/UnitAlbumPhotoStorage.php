<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\UnitAlbumPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Bewaart reeds (client-side) gecomprimeerde albumfoto's op de privé-disk,
 * zonder backend-resize (WINPROX_RULES.md §7). Pad bevat tenant-id zodat de
 * tenant-purge de hele map kan wissen.
 */
class UnitAlbumPhotoStorage
{
    public const DIRECTORY = 'unit-album';

    public function store(UploadedFile $file, int $tenantId, int $unitId, string $day): string
    {
        $path = $file->store(self::DIRECTORY.'/'.$tenantId.'/'.$unitId.'/'.$day, UnitAlbumPhoto::DISK);

        if (! is_string($path) || $path === '') {
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
}
