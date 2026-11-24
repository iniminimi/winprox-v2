<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateScreenSoundAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Screen $screen, int $tenantId, ?int $actorUserId, bool $enabled): Screen
    {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        return DB::transaction(function () use ($screen, $tenantId, $actorUserId, $enabled) {
            $locked = Screen::query()->whereKey($screen->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('screen_not_found');
            }

            $locked->update(['display_sound_enabled' => $enabled]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.sound_updated',
                modelType: Screen::class,
                modelId: $locked->id,
                payload: [
                    'screen_id' => $locked->id,
                    'display_sound_enabled' => $enabled,
                ],
            );

            return $locked;
        });
    }
}
