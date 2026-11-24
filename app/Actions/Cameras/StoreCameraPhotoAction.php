<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\Camera;
use App\Models\CameraPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreCameraPhotoAction
{
    public function handle(Camera $camera, UploadedFile $file): CameraPhoto
    {
        if (! $camera->forwarding_enabled || ! $camera->isLinked()) {
            throw ValidationException::withMessages([
                'photo' => [__('camera.errors.forwarding_off')],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $path = sprintf(
            'cameras/%d/%d/%s.%s',
            $camera->tenant_id,
            $camera->id,
            Str::lower(Str::ulid()),
            $extension === 'jpeg' ? 'jpg' : $extension,
        );

        Storage::disk(CameraPhoto::DISK)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        if (! Storage::disk(CameraPhoto::DISK)->exists($path)) {
            throw ValidationException::withMessages([
                'photo' => [__('camera.errors.store')],
            ]);
        }

        $camera->update(['last_seen_at' => now()]);

        return CameraPhoto::query()->create([
            'tenant_id' => $camera->tenant_id,
            'camera_id' => $camera->id,
            'path' => $path,
            'mime' => (string) ($file->getMimeType() ?: 'image/jpeg'),
        ]);
    }
}
