<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CameraPhoto;
use App\Models\User;

class CameraPhotoPolicy
{
    public function view(User $user, CameraPhoto $photo): bool
    {
        return app(CameraPolicy::class)->view($user, $photo);
    }
}
