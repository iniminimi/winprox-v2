<?php

declare(strict_types=1);

namespace App\Http\Controllers\PhotoAlbum;

use App\Http\Controllers\Controller;
use App\Models\UnitAlbumPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Toont een albumfoto in beheer. Privé-disk: alleen via Policy, nooit gecachet.
 */
class UnitAlbumPhotoFileController extends Controller
{
    public function __invoke(UnitAlbumPhoto $photo): StreamedResponse
    {
        $this->authorize('view', $photo);

        $disk = Storage::disk(UnitAlbumPhoto::DISK);
        abort_unless($disk->exists((string) $photo->path), 404);

        return $disk->response((string) $photo->path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
