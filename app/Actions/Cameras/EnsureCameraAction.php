<?php

declare(strict_types=1);

namespace App\Actions\Cameras;

use App\Models\Camera;
use App\Models\Unit;

class EnsureCameraAction
{
    public function handle(Unit $unit, int $tenantId): Camera
    {
        return Camera::query()->firstOrCreate(
            ['unit_id' => $unit->id],
            [
                'tenant_id' => $tenantId,
                'forwarding_enabled' => false,
            ],
        );
    }
}
