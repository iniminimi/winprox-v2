<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cameras\StoreCameraPhotoAction;
use App\Http\Requests\Cameras\StoreCameraPhotoRequest;
use App\Models\Camera;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class CameraPhotoController extends Controller
{
    public function store(StoreCameraPhotoRequest $request, StoreCameraPhotoAction $store): JsonResponse
    {
        $camera = $request->attributes->get('camera');
        abort_unless($camera instanceof Camera, 401);

        $file = $request->file('photo');
        abort_unless($file instanceof UploadedFile, 422);

        $photo = $store->handle($camera, $file);

        return $this->success([
            'id' => $photo->id,
        ], 201);
    }
}
