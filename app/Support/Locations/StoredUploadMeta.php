<?php

namespace App\Support\Locations;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class StoredUploadMeta
{
    /**
     * @return array{mime_type: ?string, file_size_bytes: ?int}
     */
    public static function fromUpload(TemporaryUploadedFile $file, string $storedPath): array
    {
        $mimeType = null;
        $fileSize = null;

        try {
            $mimeType = $file->getMimeType();
        } catch (Throwable) {
            try {
                $mimeType = $file->getClientMimeType();
            } catch (Throwable) {
                $mimeType = null;
            }
        }

        try {
            $fileSize = $file->getSize();
        } catch (Throwable) {
            try {
                $disk = Storage::disk('public');
                if ($storedPath !== '' && $disk->exists($storedPath)) {
                    $fileSize = $disk->size($storedPath);
                }
            } catch (Throwable) {
                $fileSize = null;
            }
        }

        return [
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
        ];
    }
}
