<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteUnitBackgroundPhotoAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, ?int $actorUserId = null): Unit
    {
        $path = $unit->background_photo_path;

        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }

        $unit->update(['background_photo_path' => null]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $unit->tenant_id,
            action: 'unit.background_photo_deleted',
            modelType: Unit::class,
            modelId: (int) $unit->id,
            payload: [
                'unit_id' => $unit->id,
                'path' => $path,
            ],
        );

        return $unit;
    }
}
