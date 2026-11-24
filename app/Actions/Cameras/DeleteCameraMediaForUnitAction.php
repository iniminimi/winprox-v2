<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\CameraPhoto;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;

class DeleteCameraMediaForUnitAction
{
    public function handle(Unit $unit): void
    {
        $disk = Storage::disk(CameraPhoto::DISK);

        CameraPhoto::query()
            ->whereIn('camera_id', $unit->cameras()->select('id'))
            ->orderBy('id')
            ->each(function (CameraPhoto $photo) use ($disk): void {
                $disk->delete((string) $photo->path);
            });
    }
}
