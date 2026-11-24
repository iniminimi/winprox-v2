<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\ClockDisplayImage;
use App\Models\Screen;
use App\Support\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UploadScreenImageAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(UploadedFile $file, Screen $screen, int $tenantId, ?int $actorUserId): ClockDisplayImage
    {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $count = ClockDisplayImage::query()
            ->where('tenant_id', $tenantId)
            ->where('screen_id', $screen->id)
            ->count();

        if ($count >= ClockDisplayImage::MAX_PER_SCOPE) {
            throw new InvalidArgumentException('album_full');
        }

        return DB::transaction(function () use ($file, $screen, $tenantId, $actorUserId) {
            $path = $file->store('clock-display-album/screen-'.$screen->id, 'public');
            if ($path === false) {
                throw new InvalidArgumentException('upload_failed');
            }

            $image = ClockDisplayImage::query()->create([
                'tenant_id' => $tenantId,
                'clock_point_id' => null,
                'screen_id' => $screen->id,
                'path' => $path,
                'sort_order' => (int) ClockDisplayImage::query()->where('tenant_id', $tenantId)->max('sort_order') + 1,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.image_uploaded',
                modelType: ClockDisplayImage::class,
                modelId: $image->id,
                payload: [
                    'screen_id' => $screen->id,
                    'unit_id' => $screen->unit_id,
                ],
            );

            return $image;
        });
    }
}
