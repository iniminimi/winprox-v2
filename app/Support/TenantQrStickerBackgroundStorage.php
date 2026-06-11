<?php

namespace App\Support;

use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantQrStickerBackgroundStorage
{
    public function store(UploadedFile $file, int $tenantId, QrStickerSheetTemplate $template): string
    {
        return $file->store(
            'tenant-qr-sticker-backgrounds/'.$tenantId.'/'.$template->value,
            'public',
        );
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
