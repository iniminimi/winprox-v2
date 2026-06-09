<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TenantPortalBackgroundStorage
{
    public function store(UploadedFile $file, int $tenantId): string
    {
        return $file->store("tenant-portal-backgrounds/{$tenantId}", 'public');
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
