<?php

namespace App\Support;

use App\Models\AbsenceRequestPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Slaat reeds (client-side) gecomprimeerde foto's op zonder backend-resize.
 * Privé-disk: een ziektebriefje is geen publieke meldingfoto.
 */
class AbsenceRequestPhotoStorage
{
    public const DIRECTORY = 'absence-request-photos';

    public function storePrecompressedCopy(UploadedFile $file, int $tenantId): string
    {
        return $file->store(self::DIRECTORY.'/'.$tenantId, AbsenceRequestPhoto::DISK);
    }

    public function delete(string $path): void
    {
        if ($path !== '') {
            Storage::disk(AbsenceRequestPhoto::DISK)->delete($path);
        }
    }
}
