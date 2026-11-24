<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CameraPhoto;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraPhotoFileController extends Controller
{
    public function __invoke(CameraPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $photo);

        $disk = Storage::disk(CameraPhoto::DISK);
        abort_unless($disk->exists((string) $photo->path), 404);

        return $disk->response((string) $photo->path, null, [
            'Content-Type' => (string) $photo->mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
