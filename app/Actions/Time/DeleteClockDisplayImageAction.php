<?php

namespace App\Actions\Time;

use App\Models\ClockDisplayImage;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DeleteClockDisplayImageAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        ClockDisplayImage $image,
        int $tenantId,
        ?int $actorUserId,
    ): void {
        if ((int) $image->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        DB::transaction(function () use ($image, $tenantId, $actorUserId) {
            $image->delete();
            Storage::disk('public')->delete($image->path);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'clock_display.image_deleted',
                modelType: ClockDisplayImage::class,
                modelId: $image->id,
                payload: [
                    'clock_point_id' => $image->clock_point_id,
                    'all_clock_points' => $image->clock_point_id === null,
                ],
            );
        });
    }
}
