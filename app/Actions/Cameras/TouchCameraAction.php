<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\Camera;

class TouchCameraAction
{
    public function handle(Camera $camera): void
    {
        $camera->update(['last_seen_at' => now()]);
    }
}
