<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateUnitBackgroundPhotoAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, UploadedFile $photo, ?int $actorUserId = null): Unit
    {
        if ($unit->background_photo_path !== null && $unit->background_photo_path !== '') {
            Storage::disk('public')->delete($unit->background_photo_path);
        }

        $path = $photo->store('unit-backgrounds', 'public');

        $unit->update(['background_photo_path' => $path]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $unit->tenant_id,
            action: 'unit.background_photo_updated',
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
